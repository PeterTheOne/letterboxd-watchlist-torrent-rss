<?php

include_once('config.php');
include_once('parsers/kat.php');
include_once('parsers/extratorrent.php');

function updateWatchlist(PDO $pdo) {
    $dom = new DOMDocument();

    $contents = file_get_contents(LETTERBOXD_URL);
    $contentsUTF8 = mb_convert_encoding($contents, 'HTML-ENTITIES', "UTF-8");
    $dom->loadHTML($contentsUTF8);
    $xpath = new DomXPath($dom);
    /** @var $posterNodeList DOMNodeList */
    $posterNodeList = $xpath->query("//div[contains(@class, 'poster')]");

    $i = 1;
    $films = array();
    while($posterNodeList->length > 0 && $contents !== false && $i <= MAX_WATCHLIST_PAGES_TO_FETCH) {

        foreach($posterNodeList as $posterNode) {
            $imgAltNode = $xpath->query("img/@alt", $posterNode)->item(0);
            $dataFilmSlugNode = $xpath->query("@data-film-slug", $posterNode)->item(0);

            /** @var $node DOMElement */
            $film = new ArrayObject();
            $film->title = trim($imgAltNode->textContent);
            $film->letterboxdSlug = trim($dataFilmSlugNode->textContent);

            if ($film->title === '') {
                continue;
            }
            $films[] = $film;
        }
        $i++;

        $contents = file_get_contents(LETTERBOXD_URL . 'page/' . $i . '/');
        $contentsUTF8 = mb_convert_encoding($contents, 'HTML-ENTITIES', "UTF-8");
        $dom->loadHTML($contentsUTF8);
        $xpath = new DomXPath($dom);
        /** @var $posterNodeList DOMNodeList */
        $posterNodeList = $xpath->query("//div[contains(@class, 'poster')]");
    }

    $insertStatement = $pdo->prepare('
        INSERT OR IGNORE INTO films (
            title
        ) VALUES (
            :title
        );
    ');
    $updateLetterboxdSlugStatement = $pdo->prepare('
        UPDATE films
            SET
                letterboxdSlug = :letterboxdSlug
            WHERE
                title = :title;
    ');
    foreach ($films as $film) {
        $insertStatement->bindParam(':title', $film->title);
        $insertStatement->execute();

        $updateLetterboxdSlugStatement->bindParam(':title', $film->title);
        $updateLetterboxdSlugStatement->bindParam(':letterboxdSlug', $film->letterboxdSlug);
        $updateLetterboxdSlugStatement->execute();
    }

    $filmsTitles = array_map(function($film) { return $film->title; }, $films);
    $deleteStatement = $pdo->prepare('DELETE FROM films WHERE title NOT IN ("' . implode('", "', $filmsTitles) . '")');
    if (count($filmsTitles) > 10) { /* sanity check */
        $deleteStatement->execute();
    }
}

function searchForTorrent(PDO $pdo, $sites, $titleWhitelist, $titleBlacklist, $films) {
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
        //echo "<br />" . $film->title . "<br />";
        $torrents = searchTorrentSites($sites, $titleWhitelist, $titleBlacklist, $film);
        
        if ($torrents === false) {
            $updateSearchedStatement->bindParam(':title', $film->title);
            $updateSearchedStatement->execute();
        } else {
            $maxSeeds = 0;
            foreach ($torrents as $torrent) {
                if (intval($torrent->seeds) > $maxSeeds) {
                    $maxSeeds = intval($torrent->seeds);
                    $bestTorrent = $torrent;
                }
            }

            /*echo '---------' . "<br />";
            echo 'max seeds: ' . $maxSeeds . "<br />";
            echo 'seeds: ' . $bestTorrent->seeds .', title: ' . $bestTorrent->title . '<br />';
            echo '---------' . "<br />";*/
            $updateFoundStatement->bindParam(':torrent', $bestTorrent->torrentLink);
            $updateFoundStatement->bindParam(':torrentUrl', $bestTorrent->torrentUrl);
            $updateFoundStatement->bindParam(':title', $film->title);
            $updateFoundStatement->execute();
        }
    }
}

function searchTorrentSites($sites, $titleWhitelist, $titleBlacklist, $film) {
    $torrents = array();
    
    foreach ($sites as $site) {
        switch ($site) {
            case 'kickasstorrents':
                //echo '--- site: ' . $site . "<br />";
                $site = new KickassTorrents();
                break;
            case 'extratorrent':
                //echo '--- site: ' . $site . "<br />";
                $site = new ExtraTorrent();
                break;
            default:
                continue 2; // unknown parameter, try next site
        }

        $results = parseTorrentResults($titleWhitelist, $titleBlacklist, $site, $film, $torrents);

        if($results !== false) {
            foreach ($results as $torrent) {
                $torrents[] = $torrent;
            }
        }
    }
    
    if (count($torrents) === 0) {
        return false;
    } else {
        return $torrents;
    }
}

function parseTorrentResults($titleWhitelist, $titleBlacklist, $site, $film) {
    $content = file_get_contents( $site->getSearchURL($film->title) );

    // If http response header mentions that content is gzipped, then uncompress it
    foreach($http_response_header as $c => $h) {
        if(stristr($h, 'content-encoding') && stristr($h, 'gzip')) {
            // Now lets uncompress the compressed data
            $content = gzinflate(substr($content, 10, -8));
        }
    }
    
    $contentDecoded = html_entity_decode($content);
    if ($content === false || trim($contentDecoded) === '') {
        return false;
    }
    try {
        $rss = new SimpleXMLElement($contentDecoded, LIBXML_NOWARNING | LIBXML_NOERROR);
    } catch (Exception $exception) {
        return false;
    }

    $torrents = $site->parseResults($rss);
    return filterTorrents($titleWhitelist, $titleBlacklist, $torrents);
}

function filterTorrents($titleWhitelist, $titleBlacklist, $torrents) {
    $filteredTorrents = array();
    foreach ($torrents as $torrent) {

        $min_filesize = MINIMUM_FILESIZE * 1024 * 1024 * 1024;
        $max_filesize = MAXIMUM_FILESIZE * 1024 * 1024 * 1024;

        if ($torrent->seeds < MINIMUM_SEEDS) {
            continue;
        }
        if ( ($min_filesize > 0 && $torrent->size < $min_filesize) ||
            ($max_filesize > 0 && $torrent->size > $max_filesize) ) {
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
        //echo 'seeds: ' . $torrent->seeds .', title: ' . $torrent->title . '<br />';
        $filteredTorrents[] = $torrent;
    }
    return $filteredTorrents;
}

try {
    /**
     * parse letterboxd
     */
    updateWatchlist($pdo);

    //exit();
    /**
     * look for films that have not been searched for
     */
    $filmsNotSearchedQuery = $pdo->query('SELECT title FROM films WHERE searched = 0 ORDER BY created LIMIT ' . LIMIT_FIND_NOT_SEARCHED_YET . ';');
    $filmsNotSearchedQuery->execute();
    $filmsNotSearched = $filmsNotSearchedQuery->fetchAll();
    searchForTorrent($pdo, $sites, $titleWhitelist, $titleBlacklist, $filmsNotSearched);

    /**
     * look for films previously haven't been found
     */
    $filmsNotFoundQuery = $pdo->query('SELECT title FROM films WHERE searched = 1 AND found = 0 ORDER BY lastSearchDate LIMIT ' . LIMIT_FIND_NOT_FOUND_YET . ';');
    $filmsNotFoundQuery->execute();
    $filmsNotFound = $filmsNotFoundQuery->fetchAll();
    searchForTorrent($pdo, $sites, $titleWhitelist, $titleBlacklist, $filmsNotFound);

} catch (PDOException $e) {
    //return 'PDOException';
}
