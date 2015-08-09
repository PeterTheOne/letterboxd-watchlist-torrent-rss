<?php

include_once('config.php');

if (POORMANSCRON) {
    include_once('cron.php');
}

$filmsFound = $database->getFoundFilmsOrderByFoundDate();

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
                <title><?php echo $film->title . ($film->year ? ' (' . $film->year . ')' : ''); ?></title>
                <description><?php echo $film->title; ?></description>
                <pubDate><?php echo (new DateTime($film->foundDate))->format(DATETIME::RSS); ?></pubDate>
<?php if ($film->torrentMagnet) {?>
                <torrent:magnetURI><![CDATA[<?php echo $film->torrentMagnet; ?>]]></torrent:magnetURI>
<?php }?>
<?php if ($film->torrentFile) {?>
                <link><?php echo $film->torrentFile; ?></link>
                <enclosure url="<?php echo $film->torrentFile; ?>" length="" type="application/x-bittorrent" />
<?php }?>
            </item>
        <?php
        }
        ?>
    </channel>
</rss>