<?php

include_once('config.php');
include_once('parsers/KickassTorrentsParser.php');
include_once('parsers/ExtraTorrentParser.php');

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

    updateYear($database);
}

function updateYear(\LetterBoxdWatchlistRss\DatabaseAbstract $database, $filmsWithoutYear = null) {
    if (!$filmsWithoutYear) {
        $filmsWithoutYear = $database->getFilmsWithoutYearNotFound();
    }

    $dom = new DOMDocument();
    foreach ($filmsWithoutYear as $film) {
        if ($film->year) {
            continue;
        }
        $contents = file_get_contents(LETTERBOXD_BASE_URL . '/ajax/poster' . $film->letterboxdSlug . 'menu/linked/125x187/');
        $contentsUTF8 = mb_convert_encoding($contents, 'HTML-ENTITIES', "UTF-8");
        $dom->loadHTML($contentsUTF8);
        $xpath = new DomXPath($dom);
        /** @var $posterNodeList DOMNodeList */
        $yearNode = $xpath->query("//div[contains(@class, 'poster')]/@data-film-release-year")->item(0);
        $year = trim($yearNode->textContent);
        $database->changeYear($film->title, $year);
    }
}

function searchForTorrent(\LetterBoxdWatchlistRss\DatabaseAbstract $database, $sites, $titleWhitelist, $titleBlacklist, $films) {
    foreach ($films as $film) {
        if (ENVIRONMENT === 'development') {
            echo '<br />' . $film->title . ($film->year ? ' (' . $film->year . ')' : '') . '<br />';
            echo 'min seeds: ' . MINIMUM_SEEDS . ', min size: ' . MINIMUM_FILESIZE . 'GB <br />';
        }
        $torrents = searchTorrentSites($sites, $titleWhitelist, $titleBlacklist, $film);
        
        if ($torrents === false) {
            $database->setSearched($film->title);
        } else {
            $maxSeeds = 0;
            foreach ($torrents as $torrent) {
                if (intval($torrent->seeds) > $maxSeeds) {
                    $maxSeeds = intval($torrent->seeds);
                    $bestTorrent = $torrent;
                }
            }
            if (isset($bestTorrent)) {
                if (ENVIRONMENT === 'development') {
                    echo '---------' . "<br />";
                    echo 'max seeds: ' . $maxSeeds . "<br />";
                    echo 'seeds: ' . $bestTorrent->seeds .', title: ' . $bestTorrent->title . '<br />';
                    echo '---------' . "<br />";
                }
                $database->setFound($film->title, $bestTorrent->torrentInfo, $bestTorrent->torrentMagnet, $bestTorrent->torrentFile, $bestTorrent->size);
            }
        }
    }
}

function searchTorrentSites($sites, $titleWhitelist, $titleBlacklist, $film) {
    $torrents = array();
    
    foreach ($sites as $site) {
        switch ($site) {
            case 'kickasstorrents':
                if (ENVIRONMENT === 'development') echo '--- site: ' . $site . "<br />";
                $site = new KickassTorrentsParser();
                break;
            case 'extratorrent':
                if (ENVIRONMENT === 'development') echo '--- site: ' . $site . "<br />";
                $site = new ExtraTorrentParser();
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

function parseTorrentResults($titleWhitelist, $titleBlacklist, TorrentSearchParserAbstract $site, $film) {
    $content = file_get_contents( $site->getSearchURL($film->title, $film->year) );

    if ($content === false) {
        return false;
    }

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
    $logTorrent = '';
    foreach ($torrents as $torrent) {
        if (ENVIRONMENT === 'development') {
            if($logTorrent) echo $logTorrent . '<br />';
            $logTorrent = 'seeds: ' . $torrent->seeds . ', size: ' . $torrent->size . ', title: ' . $torrent->title;
        }

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
        if (ENVIRONMENT === 'development')
            $logTorrent = '<strong>' . $logTorrent . '</strong>';
        $filteredTorrents[] = $torrent;
    }
    return $filteredTorrents;
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
    searchForTorrent($database, $sites, $titleWhitelist, $titleBlacklist, $filmsNotSearched);

    /**
     * look for films previously haven't been found
     */
    $filmsNotFound = $database->getFilmTitlesSearchedNotFoundLimit(LIMIT_FIND_NOT_FOUND_YET);
    searchForTorrent($database, $sites, $titleWhitelist, $titleBlacklist, $filmsNotFound);

} catch (PDOException $e) {
    //return 'PDOException';
}
