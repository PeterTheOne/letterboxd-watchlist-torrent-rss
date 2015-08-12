<?php

include_once('config.php');

if (POORMANSCRON) {
    include_once('cron.php');
}

// Remove special characters
function cleanURL($url) {
    if(!$url) return $url;

    $url = str_replace('http://', '', $url);

    $url_parts = explode('?', $url);
    $base = $url_parts[0];

    $cleanURL = 'http://';
    if( stristr($base, '/') !== FALSE ) {
        foreach (explode('/', $base) as $chunk) {
            $cleanURL .= rawurlencode($chunk) . '/';
        }
        $cleanURL = substr($cleanURL, 0, -1);
    }

    if( count($url_parts) > 1 ) {
        $params = $url_parts[1];
        $cleanURL .= '?';

        foreach (explode('&', $params) as $chunk) {
            $param = explode("=", $chunk);

            if ($param) {
                $cleanURL .= rawurlencode($param[0]) . '=' . rawurlencode($param[1]) . '&';
            }
        }
        $cleanURL = substr($cleanURL, 0, -1);
    }

    return $cleanURL;
}

$filmsFound = $database->getFoundFilmsOrderByFoundDate();

header('Content-Type: application/xml; charset=utf-8', true);
$xml = new DOMDocument("1.0", "UTF-8");

// RSS element
$rss = $xml->createElement("rss");
$rss->setAttribute("version","2.0"); //set RSS version
$rss->setAttribute( "xmlns:torrent", "http://xmlns.ezrss.it/0.1/" );
$rss->setAttribute( "xmlns:atom", "http://www.w3.org/2005/Atom" );

// Channel element
$channel = $xml->createElement( "channel" );
$channelTitle = $xml->createElement( "title", "letterboxd watchlist rss torrent" );
$channelDescription = $xml->createElement( "description", "letterboxd watchlist rss torrent" );
$channelLanguage = $xml->createElement( "language", "en-us" );
$feedURL = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . "{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
$channelLink = $xml->createElement( "link", $feedURL );
$channelAtomLink = $xml->createElement( "atom:link" );
$channelAtomLink->setAttribute( "href", $feedURL );
$channelAtomLink->setAttribute( "rel", "self" );
$channelAtomLink->setAttribute( "type", "application/rss+xml" );

$channel->appendChild( $channelTitle );
$channel->appendChild( $channelDescription );
$channel->appendChild( $channelLanguage );
$channel->appendChild( $channelLink );
$channel->appendChild( $channelAtomLink );

// Items
foreach ($filmsFound as $film) {
    $item = $xml->createElement( "item" );

    $filmTitle = $film->title . ($film->year ? ' (' . $film->year . ')' : '');
    $filmPubDate = (new DateTime($film->foundDate))->format(DATETIME::RSS);
    $torrentFile = cleanURL($film->torrentFile);
    $torrentInfo = cleanURL($film->torrentInfo);

    // Item subelements
    $title = $xml->createElement( "title", $filmTitle );
    $description = $xml->createElement( "description", $filmTitle );
    $pubDate = $xml->createElement( "pubDate", $filmPubDate );
    $guid = $xml->createElement( "guid" );
    $guid->appendChild( new DOMText( $torrentInfo ) );
    $link = $xml->createElement( "link" );

    $enclosure = $xml->createElement( "enclosure" );
    $enclosure->setAttribute( "length", $film->torrentSize );
    $enclosure->setAttribute( "type", "application/x-bittorrent" );

    $torrentContentLength = $xml->createElement( "torrent:contentLength", $film->torrentSize );

    if ($film->torrentMagnet) {
        $enclosure->setAttribute( "url", $film->torrentMagnet );
        $link->appendChild( new DOMText( $film->torrentMagnet ) );     

        $torrentMagnetURI = $xml->createElement( "torrent:magnetURI" );
        $torrentMagnetURI->appendChild( new DOMText( $film->torrentMagnet ) );
    } else {
        $enclosure->setAttribute( "url", $torrentFile );
        $link->appendChild( new DOMText( $torrentFile ) );
    }

    $item->appendChild( $title );
    $item->appendChild( $description );
    $item->appendChild( $pubDate );
    $item->appendChild( $guid );
    $item->appendChild( $link );

    if ($film->torrentMagnet) {
        $item->appendChild( $torrentMagnetURI );
    }

    $item->appendChild( $enclosure );
    $item->appendChild( $torrentContentLength );

    // Item complete
    $channel->appendChild( $item );
}

$rss->appendChild( $channel );
$xml->appendChild( $rss );

// Parse the XML
echo $xml->saveXML();
