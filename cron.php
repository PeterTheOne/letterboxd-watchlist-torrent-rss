<?php

include_once('config.php');
include_once('functions.php');
include_once('parsers/KickassTorrentsParser.php');
include_once('parsers/ExtraTorrentParser.php');

function updateWatchlist(\LetterBoxdWatchlistRss\DatabaseAbstract $database) {
    $dom = new DOMDocument();

    $contents = file_get_contents(LETTERBOXD_URL);
    $contentsUTF8 = mb_convert_encoding($contents, 'HTML-ENTITIES', "UTF-8");

    $previous_value = libxml_use_internal_errors(TRUE); /* Don't display warnings */
    $dom->loadHTML($contentsUTF8);
    libxml_clear_errors();
    libxml_use_internal_errors($previous_value);

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

        $previous_value = libxml_use_internal_errors(TRUE); /* Don't display warnings */
        $dom->loadHTML($contentsUTF8);
        libxml_clear_errors();
        libxml_use_internal_errors($previous_value);

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
    if (ENVIRONMENT === 'development') echo '<table class="table table-condensed table-hover">';

    foreach ($films as $film) {
        $torrents = searchTorrentSites($sites, $titleWhitelist, $titleBlacklist, $film);
        
        if ($torrents === false) {
            $database->setSearched($film->title);
        } else {
            $maxSeeds = 0;
            foreach ($torrents as $torrent) {
                if ($torrent->seeds > $maxSeeds) {
                    $maxSeeds = $torrent->seeds;
                    $bestTorrent = $torrent;
                }
            }
            if (isset($bestTorrent) && ($bestTorrent->torrentFile || $bestTorrent->torrentMagnet)) {
                if (ENVIRONMENT === 'development')
                    echo '<tr class="success"><td colspan="4"><strong>' . $bestTorrent->title . '</strong> (' . human_filesize($bestTorrent->size) . ') is most seeded torrent with ' . $bestTorrent->seeds . ' seeds!</td></tr>';
                $database->setFound($film->title, $bestTorrent->title, $bestTorrent->torrentInfo, $bestTorrent->torrentInfoHash, $bestTorrent->torrentMagnet, $bestTorrent->torrentFile, $bestTorrent->size);
            }
        }
    }

    if (ENVIRONMENT === 'development') echo '</table>';
}

function searchTorrentSites($sites, $titleWhitelist, $titleBlacklist, $film) {
    $torrents = array();
    
    foreach ($sites as $site) {
        if (ENVIRONMENT === 'development')
            echo '<tr>
                <th>' . $film->title . ($film->year ? ' (' . $film->year . ')' : '') . ' on ' . $site . '</th>
                <th>Seeds</th>
                <th>Size</th>
                <th>Verdict</th>
            </tr>';

        switch ($site) {
            case 'kickasstorrents':
                $site = new KickassTorrentsParser();
                break;
            case 'extratorrent':
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
    // Remove all characters except A-Z, a-z, 0-9, dots, hyphens and spaces
    // Note that the hyphen must go last not to be confused with a range (A-Z)
    // and the dot, being special, is escaped with \
    $searchTerms = preg_replace('/[^A-Za-z0-9\. -]/', '', $film->title . ' ' . $film->year);

    $content = @file_get_contents( $site->getSearchURL($searchTerms) );

    if ($content === false) {
        if (ENVIRONMENT === 'development') echo '<tr class="danger"><td colspan="4">Error parsing results: file_get_contents</td></tr>';
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
        if (ENVIRONMENT === 'development') echo '<tr class="danger"><td colspan="4">Error parsing results: html_entity_decode</td></tr>';
        return false;
    }
    try {
        $rss = new SimpleXMLElement($contentDecoded, LIBXML_NOWARNING | LIBXML_NOERROR);
    } catch (Exception $exception) {
        if (ENVIRONMENT === 'development') echo '<tr class="danger"><td colspan="4">Error parsing results: SimpleXMLElement</td></tr>';
        return false;
    }

    $torrents = $site->parseResults($rss);
    return filterTorrents($titleWhitelist, $titleBlacklist, $searchTerms, $torrents);
}

function filterTorrents($titleWhitelist, $titleBlacklist, $searchTerms, $torrents) {
    if (count($torrents) === 0 && ENVIRONMENT === 'development')
        echo '<tr><td colspan="4">No results.</td></tr>';

    $filteredTorrents = array();
    $logTorrent = '';
    $searchTerms = explode(" ", $searchTerms);
    foreach ($torrents as $torrent) {
        $logTorrent = '<tr%s>
            <td%s>' . $torrent->title . '</td>
            <td%s>' . $torrent->seeds . '</td>
            <td%s>' . human_filesize($torrent->size) . '</td>
            <td>%s</td>
        </tr>';

        $min_filesize = MINIMUM_FILESIZE * 1024 * 1024 * 1024;
        $max_filesize = MAXIMUM_FILESIZE * 1024 * 1024 * 1024;

        if ($torrent->seeds < MINIMUM_SEEDS) {
            if (ENVIRONMENT === 'development') echo sprintf($logTorrent, '', '', ' class="info"', '', 'Not enough seeds (at least ' . MINIMUM_SEEDS . ' needed)');
            continue;
        }
        if ($min_filesize > 0 && $torrent->size < $min_filesize) {
            if (ENVIRONMENT === 'development') echo sprintf($logTorrent, '', '', '', ' class="info"', 'Filesize too small (at least ' . human_filesize($min_filesize) . ' needed)');
            continue;
        }
        if ($max_filesize > 0 && $torrent->size > $max_filesize) {
            if (ENVIRONMENT === 'development') echo sprintf($logTorrent, '', '', '', ' class="info"', 'Filesize too big (less than ' . human_filesize($max_filesize) . ' needed)');
            continue;
        }
        foreach ($searchTerms as $word) {
            if (stripos($torrent->title, $word) === false) {
                if (ENVIRONMENT === 'development') echo sprintf($logTorrent, '', ' class="info"', '', '', 'Search term "' . $word . '" not found in torrent title');
                continue 2; /* continue outer loop if word is not found */
            }
        }
        $whiteWordFound = false;
        foreach ($titleWhitelist as $word) {
            if (stripos($torrent->title, $word) !== false) {
                $whiteWordFound = true;
                break;
            }
        }
        if (!$whiteWordFound) {
            if (ENVIRONMENT === 'development') echo sprintf($logTorrent, '', ' class="info"', '', '', 'No term from whitelist found in torrent title');
            continue; /* continue outer loop if no word is found */
        }
        foreach ($titleBlacklist as $word) {
            if (stripos($torrent->title, $word) !== false) {
                if (ENVIRONMENT === 'development') echo sprintf($logTorrent, '', ' class="info"', '', '', '"' . $word . '" from blacklist found in torrent title');
                continue 2; /* continue outer loop if word is found */
            }
        }
        if (ENVIRONMENT === 'development') echo sprintf($logTorrent, ' class="success"', '', '', '', 'Acceptable');
        $filteredTorrents[] = $torrent;
    }
    return $filteredTorrents;
}

try {
    if (ENVIRONMENT === 'development') : ?>
<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>letterboxd-watchlist-rss - Cronjob</title>

        <link rel="stylesheet" href="css/bootstrap.min.css">
    </head>
    <body>
        <div class="container-fluid">
            <div class="row">
                <div class="col-xs-12">
                    <h1>letterboxd-watchlist-rss - Cronjob</h1>

                    <h2>Updating watchlist...</h2>
    <?php endif;

    /**
     * parse letterboxd
     */
    updateWatchlist($database);

    if (ENVIRONMENT === 'development') : ?>
                    <h2>Searching for films, first time...</h2>
    <?php endif;

    /**
     * look for films that have not been searched for
     */
    $filmsNotSearched = $database->getFilmTitlesNotSearchedLimit(LIMIT_FIND_NOT_SEARCHED_YET);
    searchForTorrent($database, $sites, $titleWhitelist, $titleBlacklist, $filmsNotSearched);

    if (ENVIRONMENT === 'development') : ?>
                    <h2>Searching for films, previously not found...</h2>
    <?php endif;

    /**
     * look for films previously haven't been found
     */
    $filmsNotFound = $database->getFilmTitlesSearchedNotFoundLimit(LIMIT_FIND_NOT_FOUND_YET);
    searchForTorrent($database, $sites, $titleWhitelist, $titleBlacklist, $filmsNotFound);

    if (ENVIRONMENT === 'development') : ?>
                </div><!-- .col-xs-12 -->
            </div><!-- .row -->
        </div><!-- .container-fluid -->
    </body>
</html>
    <?php endif;
} catch (PDOException $e) {
    //return 'PDOException';
}
