<?php

error_reporting(E_ERROR | E_PARSE);

$letterboxdUrl = 'http://letterboxd.com/petertheone/watchlist/';
$kickasstorrentUrl = 'http://kickass.to/usearch/category%3Amovies%20';

$dom = new DOMDocument();
$dom->loadHTML(file_get_contents($letterboxdUrl));
$xpath = new DomXPath($dom);
$className = 'poster';
$nodes = $xpath->query('//*[contains(@class, "' . $className . '")]');

$filmCount = 0;
$filmNotFound = 0;
$filmError = 0;

//print_r($nodes);
//exit;

$films = array();
foreach ($nodes as $node) {
    /** @var $node DOMElement */
    $film = new ArrayObject();
    $film->title = trim($node->nodeValue);
    $film->torrentLink = '';
    $films[] = $film;
    $filmCount++;
}

foreach ($films as &$film) {
    $site = file_get_contents($kickasstorrentUrl . rawurlencode($film->title) . '/?rss=1');
    $site = html_entity_decode($site);
    if ($site === false || trim($site) === '') {
        $filmNotFound++;
        continue;
    }
    try {
        $rss = new SimpleXMLElement($site, LIBXML_NOWARNING | LIBXML_NOERROR);
    } catch (Exception $exception) {
        $filmError++;
        continue;
    }

    $torrent = $rss->channel->item[0];
    $film->torrentLink = $torrent->children('http://xmlns.ezrss.it/0.1/')->magnetURI;
}

$debug = true;
if ($debug) {
    echo 'filmCount: ' . $filmCount . '<br />';
    echo 'filmNotFound: ' . $filmNotFound . '<br />';
    echo 'filmError: ' . $filmError . '<br />';

    exit;
}

header('Content-Type: application/rss+xml;');
echo "<?xml version='1.0' encoding='UTF-8'?>";

?>
<rss version='2.0'>
    <channel>
        <title>letterboxd watchlist rss torrent</title>
        <!--<link>link</link>-->
        <description>letterboxd watchlist rss torrent</description>
        <language>en-us</language>
        <item>
<?php
    foreach ($films as $film) {
?>
            <title><?php echo $film->title; ?></title>
            <link><?php echo $film->torrentLink; ?></link>
            <description><?php echo $film->title;; ?></description>
<?php
    }
?>
        </item>
    </channel>
</rss>