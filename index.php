<?php

include_once('config.php');

if (POORMANSCRON) {
    include_once('cron.php');
}

$filmsFoundQuery = $pdo->query('SELECT foundDate, title, torrent, torrentUrl FROM films WHERE found = 1 ORDER BY foundDate;');
$filmsFoundQuery->execute();
$filmsFound = $filmsFoundQuery->fetchAll();

header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";

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
                <enclosure url="<?php echo $film->torrentUrl; ?>" length="" type="application/x-bittorrent" />
            </item>
        <?php
        }
        ?>
    </channel>
</rss>