<?php

include_once('config.php');

function updateWatchlist(\LetterBoxdWatchlistRss\DatabaseAbstract $database) {
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

    foreach ($films as $film) {
        $database->addOrIgnoreTitle($film->title);
        $database->changeLetterboxdSlug($film->title, $film->letterboxdSlug);
    }

    $filmsTitles = array_map(function($film) { return $film->title; }, $films);
    $database->removeFilmsNotInTitleList($filmsTitles);
}

function searchForTorrent(\LetterBoxdWatchlistRss\DatabaseAbstract $database, $titleWhitelist, $titleBlacklist, $films) {
    foreach ($films as $film) {
        $site = file_get_contents(KICKASSTORRENT_URL . rawurlencode($film->title) . '/?rss=1');

        if ($site === false) {
            $database->setSearched($film->title);
            continue;
        }
        
        // If http response header mentions that content is gzipped, then uncompress it
        foreach($http_response_header as $c => $h) {
            if(stristr($h, 'content-encoding') && stristr($h, 'gzip')) {
                // Now lets uncompress the compressed data
                $site = gzinflate(substr($site, 10, -8));
            }
        }
        
        $siteDecoded = html_entity_decode($site);
        if ($site === false || trim($siteDecoded) === '') {
            $database->setSearched($film->title);
            continue;
        }
        try {
            $rss = new SimpleXMLElement($siteDecoded, LIBXML_NOWARNING | LIBXML_NOERROR);
        } catch (Exception $exception) {
            $database->setSearched($film->title);
            continue;
        }

        //echo "<br />" . $film->title . "<br />";
        $maxSeeds = 0;
        $torrents = array();
        foreach ($rss->channel->item as $torrentNode) {
            $torrent = new ArrayObject();
            $torrent->title = strtolower($torrentNode->children()->title);
            $torrent->seeds = $torrentNode->children('http://xmlns.ezrss.it/0.1/')->seeds;
            $torrent->size = $torrentNode->children('http://xmlns.ezrss.it/0.1/')->contentLength;
            $torrent->torrentLink = $torrentNode->children('http://xmlns.ezrss.it/0.1/')->magnetURI;
            $torrent->torrentUrl = $torrentUrl = $torrentNode->children()->enclosure->attributes()->{'url'};

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
            //echo 'seeds: ' . $torrent->seeds .', title: ' . $torrent->title .  "<br />";
            if (intval($torrent->seeds) > $maxSeeds) {
                $maxSeeds = intval($torrent->seeds);
            }
            $torrents[] = $torrent;
        }
        if (count($torrents) === 0) {
            $database->setSearched($film->title);
        } else {
            foreach ($torrents as $torrent) {
                if ($torrent->seeds >= $maxSeeds) {
                    /*echo '---------' . "<br />";
                    echo 'max: ' . $maxSeeds . "<br />";
                    echo 'seeds: ' . $torrent->seeds .', title: ' . $torrent->title .  "<br />";
                    echo '---------' . "<br />";*/
                    $database->setFound($film->title, $torrent->torrentLink, $torrent->torrentUrl);
                    break;
                }
            }
        }
    }
}

try {
    /**
     * parse letterboxd
     */
    updateWatchlist($database);

    /**
     * look for films that have not been searched for
     */
    $filmsNotSearched = $database->getFilmTitlesNotSearchedLimit(LIMIT_FIND_NOT_SEARCHED_YET);
    searchForTorrent($database, $titleWhitelist, $titleBlacklist, $filmsNotSearched);

    /**
     * look for films previously haven't been found
     */
    $filmsNotFound = $database->getFilmTitlesSearchedNotFoundLimit(LIMIT_FIND_NOT_FOUND_YET);
    searchForTorrent($database, $titleWhitelist, $titleBlacklist, $filmsNotFound);

} catch (PDOException $e) {
    //return 'PDOException';
}
