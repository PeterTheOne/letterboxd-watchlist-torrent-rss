<?php

include_once('config.php');

function updateWatchlist(PDO $pdo) {
    $dom = new DOMDocument();

    $contents = mb_convert_encoding(file_get_contents(LETTERBOXD_URL), 'HTML-ENTITIES', "UTF-8");
    $dom->loadHTML($contents);
    $xpath = new DomXPath($dom);
    $nodes = $xpath->query("//div[contains(@class, 'poster')]//a/@title");

    $i = 1;
    $films = array();
    while($nodes->length > 0) {
        foreach ($nodes as $node) {
            /** @var $node DOMElement */
            $film = new ArrayObject();
            $film->title = trim($node->textContent);
            if ($film->title === '') {
                continue;
            }
            $films[] = $film;
        }
        $i++;

        $contents = mb_convert_encoding(file_get_contents(LETTERBOXD_URL . 'page/' . $i . '/'), 'HTML-ENTITIES', "UTF-8");
        $dom->loadHTML($contents);
        $xpath = new DomXPath($dom);
        $nodes = $xpath->query("//div[contains(@class, 'poster')]//a/@title");
    }

    $insertStatement = $pdo->prepare('
        INSERT OR IGNORE INTO films (
            title
        ) VALUES (
            :title
        );
    ');
    foreach ($films as $film) {
        $insertStatement->bindParam(':title', $film->title);
        $insertStatement->execute();
    }

    $filmsTitles = array_map(function($film) { return $film->title; }, $films);
    $deleteStatement = $pdo->query('DELETE FROM films WHERE title NOT IN ("' . implode('", "', $filmsTitles) . '")');
    if (count($filmsTitles) > 10) { /* sanity check */
        $deleteStatement->execute();
    }
}

function searchForTorrent(PDO $pdo, $titleWhitelist, $titleBlacklist, $films) {
    $updateSearchedStatement = $pdo->prepare('UPDATE films SET searched = 1, lastSearchDate = datetime(\'now\', \'localtime\') WHERE title = :title;');
    $updateFoundStatement = $pdo->prepare('
        UPDATE films
            SET
                foundDate = datetime(\'now\', \'localtime\'),
                lastSearchDate = datetime(\'now\', \'localtime\'),
                searched = 1,
                found = 1,
                torrent = :torrent,
                torrentUrl = :torrentUrl
            WHERE title = :title;
    ');

    foreach ($films as $film) {
        $site = file_get_contents(KICKASSTORRENT_URL . rawurlencode($film->title) . '/?rss=1');
        $site = html_entity_decode($site);
        if ($site === false || trim($site) === '') {
            $updateSearchedStatement->bindParam(':title', $film->title);
            $updateSearchedStatement->execute();
            continue;
        }
        try {
            $rss = new SimpleXMLElement($site, LIBXML_NOWARNING | LIBXML_NOERROR);
        } catch (Exception $exception) {
            $updateSearchedStatement->bindParam(':title', $film->title);
            $updateSearchedStatement->execute();
            continue;
        }

        //echo "<br />" . $film->title . "<br />";
        $maxSeeds = 0;
        $torrents = array();
        foreach ($rss->channel->item as $torrentNode) {
            $torrent = new ArrayObject();
            $torrent->title = strtolower($torrentNode->children()->title);
            $torrent->seeds = $torrentNode->children('http://xmlns.ezrss.it/0.1/')->seeds;
            $torrent->torrentLink = $torrentNode->children('http://xmlns.ezrss.it/0.1/')->magnetURI;
            $torrent->torrentUrl = $torrentUrl = $torrentNode->children()->enclosure->attributes()->{'url'};

            if ($torrent->seeds < MINIMUM_SEEDS) {
                continue;
            }
            $whiteWordFound = false;
            foreach ($titleWhitelist as $word) {
                if (strpos($torrent->title, $word) !== false) {
                    $whiteWordFound = true;
                    break;
                }
            }
            if (!$whiteWordFound) {
                continue; /* continue outer loop if no word is found */
            }
            foreach ($titleBlacklist as $word) {
                if (strpos($torrent->title, $word) !== false) {
                    continue 2; /* continue outer loop if word is found */
                }
            }
            //echo 'seeds: ' . $torrent->seeds .', title: ' . $torrent->title .  "<br />";
            if (intval($torrent->seeds) > $maxSeeds) {
                $maxSeeds = intval($torrent->seeds);
            }
            $torrents[] = $torrent;
        }
        if (count($torrents) === 0) {
            $updateSearchedStatement->bindParam(':title', $film->title);
            $updateSearchedStatement->execute();
        } else {
            foreach ($torrents as $torrent) {
                if ($torrent->seeds >= $maxSeeds) {
                    /*echo '---------' . "<br />";
                    echo 'max: ' . $maxSeeds . "<br />";
                    echo 'seeds: ' . $torrent->seeds .', title: ' . $torrent->title .  "<br />";
                    echo '---------' . "<br />";*/
                    $updateFoundStatement->bindParam(':torrent', $torrent->torrentLink);
                    $updateFoundStatement->bindParam(':torrentUrl', $torrent->torrentUrl);
                    $updateFoundStatement->bindParam(':title', $film->title);
                    $updateFoundStatement->execute();
                    break;
                }
            }
        }
    }
}

/**
 * parse letterboxd
 */

updateWatchlist($pdo);

/**
 * look for films that have not been searched for
 */

$filmsNotSearchedQuery = $pdo->query('SELECT title FROM films WHERE searched = 0 ORDER BY created LIMIT ' . LIMIT_FIND_NOT_SEARCHED_YET . ';');
$filmsNotSearchedQuery->execute();
$filmsNotSearched = $filmsNotSearchedQuery->fetchAll();
searchForTorrent($pdo, $titleWhitelist, $titleBlacklist, $filmsNotSearched);

/**
 * look for films previously haven't been found
 */

$filmsNotFoundQuery = $pdo->query('SELECT title FROM films WHERE searched = 1 AND found = 0 ORDER BY lastSearchDate LIMIT ' . LIMIT_FIND_NOT_FOUND_YET . ';');
$filmsNotFoundQuery->execute();
$filmsNotFound = $filmsNotFoundQuery->fetchAll();
searchForTorrent($pdo, $titleWhitelist, $titleBlacklist, $filmsNotFound);