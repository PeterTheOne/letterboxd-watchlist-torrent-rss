<?php

error_reporting(E_ERROR | E_PARSE);

$letterboxdUsername = 'petertheone';

$letterboxdUrl = 'http://letterboxd.com/' . $letterboxdUsername . '/watchlist/';
define('KICKASSTORRENT_URL','http://kickass.to/usearch/category%3Amovies%20');


$dom = new DOMDocument();
$dom->loadHTML(file_get_contents($letterboxdUrl));
$xpath = new DomXPath($dom);
$nodes = $xpath->query("//div[contains(@class, 'poster')]//a/@title");

$i = 1;
$films = array();
while($nodes->length > 0 && $i < 4) {
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

    $dom = new DOMDocument();
    $dom->loadHTML(file_get_contents($letterboxdUrl . 'page/' . $i . '/'));
    $xpath = new DomXPath($dom);
    $nodes = $xpath->query("//div[contains(@class, 'poster')]//a/@title");
}

$pdo = new PDO('sqlite:database.sqlite3');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);

$pdo->query('
    CREATE TABLE IF NOT EXISTS films (
        id INTEGER PRIMARY KEY,
        created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        foundDate TIMESTAMP,
        title VARCHAR(255),
        searched BOOLEAN DEFAULT 0,
        found BOOLEAN DEFAULT 0,
        torrent TEXT,
        torrentUrl TEXT,
        UNIQUE(title)
    );
');

foreach ($films as $film) {
    $insertStatement = $pdo->prepare('
        INSERT OR IGNORE INTO films (
            title
        ) VALUES (
            :title
        );
    ');
    $insertStatement->bindParam(':title', $film->title);
    $insertStatement->execute();
}


$filmsNotSearchedQuery = $pdo->query('SELECT title FROM films WHERE searched = 0 LIMIT 5;');
$filmsNotSearchedQuery->execute();
$filmsNotSearched = $filmsNotSearchedQuery->fetchAll();
searchForTorrent($pdo, $filmsNotSearched);

$filmsNotFoundQuery = $pdo->query('SELECT title FROM films WHERE searched = 1 AND found = 0 ORDER BY RANDOM() LIMIT 1;');
$filmsNotFoundQuery->execute();
$filmsNotFound = $filmsNotFoundQuery->fetchAll();
searchForTorrent($pdo, $filmsNotFound);

function searchForTorrent(PDO $pdo, $films) {
    $updateSearchedStatement = $pdo->prepare('UPDATE films SET searched = 1 WHERE title = :title;');
    $updateFoundStatement = $pdo->prepare('
        UPDATE films
            SET
                foundDate = datetime(\'now\'),
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

            if ($torrent->seeds < 4) {
                continue;
            }
            if (strpos($torrent->title, '720p') === false &&
                    strpos($torrent->title, '1080p') === false &&
                    strpos($torrent->title, 'bdrip') === false &&
                    strpos($torrent->title, 'brrip') === false) {
                continue;
            }
            if (strpos($torrent->title, 'upscaled') !== false) {
                continue;
            }
            if (strpos($torrent->title, 'hdcam') !== false) {
                continue;
            }
            if (strpos($torrent->title, 'trailer') !== false) {
                continue;
            }
            if (strpos($torrent->title, 'yify') !== false) {
                continue;
            }
            if (strpos($torrent->title, 'ganool') !== false) {
                continue;
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

$filmsFoundQuery = $pdo->query('SELECT foundDate, title, torrent, torrentUrl FROM films WHERE found = 1;');
$filmsFoundQuery->execute();
$filmsFound = $filmsFoundQuery->fetchAll();

header('Content-Type: application/xml; charset=utf-8');
echo "<?xml version='1.0' encoding='UTF-8'?>\n";

?>
<rss version="2.0" xmlns:torrent="http://xmlns.ezrss.it/0.1/">
    <channel>
        <title>letterboxd watchlist rss torrent</title>
        <description>letterboxd watchlist rss torrent</description>
        <language>en-us</language>
<?php
    foreach ($filmsFound as $film) {
?>
        <item>
            <title><?php echo $film->title; ?></title>
            <link><?php echo $film->torrentUrl; ?></link>
            <description><?php echo $film->title; ?></description>
            <pubDate><?php echo (new DateTime($film->foundDate))->format(DATETIME::RSS); ?></pubDate>
            <enclosure url="<?php echo $film->torrentUrl; ?>" type="application/x-bittorrent" />
        </item>
<?php
    }
?>
    </channel>
</rss>
