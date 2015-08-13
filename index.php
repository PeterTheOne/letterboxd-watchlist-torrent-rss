<?php

include_once('config.php');

if (POORMANSCRON) {
    include_once('cron.php');
}

$filmsFound = $database->getFoundFilmsOrderByFoundDate();

header('Content-Type: application/xml; charset=utf-8', true);
$xml = new DOMDocument("1.0", "UTF-8");

// RSS element
$rss = $xml->createElement("rss");
$rss->setAttribute("version","2.0"); //set RSS version
$rss->setAttribute( "xmlns:torrent", "http://xmlns.ezrss.it/0.1/" );

// Channel element
$channel = $xml->createElement( "channel" );
$channelTitle = $xml->createElement( "title", "letterboxd watchlist rss torrent" );
$channelDescription = $xml->createElement( "description", "letterboxd watchlist rss torrent" );
$channelLanguage = $xml->createElement( "language", "en-us" );
$feedURL = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . "{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
$channelLink = $xml->createElement( "link", $feedURL );

$channel->appendChild( $channelTitle );
$channel->appendChild( $channelDescription );
$channel->appendChild( $channelLanguage );
$channel->appendChild( $channelLink );

// Items
foreach ($filmsFound as $film) {
    $item = $xml->createElement( "item" );

    $filmTitle = $film->title . ($film->year ? ' (' . $film->year . ')' : '');
    $filmPubDate = (new DateTime($film->foundDate))->format(DATETIME::RSS);

    // Item subelements
    $title = $xml->createElement( "title", $film->torrentTitle );
    $description = $xml->createElement( "description", $filmTitle );
    $pubDate = $xml->createElement( "pubDate", $filmPubDate );
    $guid = $xml->createElement( "guid" );
    $guid->appendChild( new DOMText( $film->torrentInfo ) );

    $item->appendChild( $title );
    $item->appendChild( $description );
    $item->appendChild( $pubDate );
    $item->appendChild( $guid );

    if ($film->torrentInfoHash) {
        $torrentInfoHash = $xml->createElement( "torrent:infoHash", $film->torrentInfoHash );
        $item->appendChild( $torrentInfoHash );
    }

    if ($film->torrentSize) {
        $torrentContentLength = $xml->createElement( "torrent:contentLength", $film->torrentSize );
        $item->appendChild( $torrentContentLength );
    }

    if ($film->torrentFile) {
        $enclosure = $xml->createElement( "enclosure" );
        $enclosure->setAttribute( "url", $film->torrentFile );
        if ($film->torrentSize) $enclosure->setAttribute( "length", $film->torrentSize );
        $enclosure->setAttribute( "type", "application/x-bittorrent" );
        $item->appendChild( $enclosure );

        $link = $xml->createElement( "link" );
        $link->appendChild( new DOMText( $film->torrentFile ) );
        $item->appendChild( $link );
    }

    if ($film->torrentMagnet) {
        $torrentMagnetURI = $xml->createElement( "torrent:magnetURI" );
        $torrentMagnetURI->appendChild( new DOMText( $film->torrentMagnet ) );
        $item->appendChild( $torrentMagnetURI );
    }

    // Item complete
    $channel->appendChild( $item );
}

$rss->appendChild( $channel );
$xml->appendChild( $rss );

// Parse the XML
echo $xml->saveXML();
