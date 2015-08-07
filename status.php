<?php

include_once('config.php');

$filmsFoundQuery = $pdo->query('SELECT title, letterboxdSlug, created, lastSearchDate, foundDate, torrent, torrentUrl FROM films ORDER BY created;');
$filmsFoundQuery->execute();
$filmsFound = $filmsFoundQuery->fetchAll();

?>
<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8" />

        <title>letterboxd-watchlist-rss - Status</title>
    </head>
    <body>
        <h1>letterboxd-watchlist-rss - Status</h1>

        <table>
            <tr>
                <th>Title</th>
                <th>Created</th>
                <th>LastSearchDate</th>
                <th>FoundDate</th>
            </tr>
            <?php
                foreach ($filmsFound as $film) {
            ?>
            <tr>
                <td><a href="<?php echo LETTERBOXD_BASE_URL . $film->letterboxdSlug; ?>"><?php echo $film->title; ?></a></td>
                <td><?php echo $film->created; ?></td>
                <td><?php echo $film->lastSearchDate; ?></td>
                <td><?php echo $film->foundDate; ?></td>
            </tr>
            <?php
                }
            ?>
        </table>
    </body>
</html>