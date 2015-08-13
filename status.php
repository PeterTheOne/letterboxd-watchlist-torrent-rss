<?php

include_once('config.php');

$filmsFound = $database->getFilmsOrderByCreated();

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
                <th>Links</th>
                <th>Torrent Title</th>
            </tr>
            <?php
                foreach ($filmsFound as $film) {
            ?>
            <tr>
                <td>
                    <a href="<?php echo LETTERBOXD_BASE_URL . $film->letterboxdSlug; ?>">
                        <?php echo $film->title . ($film->year ? ' (' . $film->year . ')' : ''); ?>
                    </a>
                </td>
                <td><?php echo $film->created; ?></td>
                <td><?php echo $film->lastSearchDate; ?></td>
                <td><?php echo $film->foundDate; ?></td>
                <td>
                    <?php if ($film->found) { ?>
                        <?php if ($film->torrentInfo) : ?><a href="<?php echo $film->torrentInfo; ?>">Info</a> |<?php endif; ?>
                        <?php if ($film->torrentMagnet) : ?><a href="<?php echo $film->torrentMagnet; ?>">Magnet</a> |<?php endif; ?>
                        <?php if ($film->torrentFile) : ?><a href="<?php echo $film->torrentFile; ?>">File</a><?php endif; ?>
                    <?php } ?>
                </td>
                <td><?php echo $film->torrentTitle; ?></td>
            </tr>
            <?php
                }
            ?>
        </table>
    </body>
</html>