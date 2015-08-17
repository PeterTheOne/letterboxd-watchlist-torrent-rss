<?php

include_once('config.php');
include_once('functions.php');

$filmsFound = $database->getFilmsOrderByCreated();

?>
<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>letterboxd-watchlist-rss - Status</title>

        <link rel="stylesheet" href="css/bootstrap.min.css">
    </head>
    <body>
        <div class="container-fluid">
            <div class="row">
                <div class="col-xs-12">
                    <h1>letterboxd-watchlist-rss - Status</h1>

                <?php if( count($filmsFound) === 0) : ?>
                    <p class="bg-warning">Nothing yet! <a href="cron.php">Start a cronjob</a></p>
                <?php else : ?>

                    <table class="table table-condensed table-hover">
                        <tr>
                            <th>Title</th>
                            <th>Added</th>
                            <th>Searched</th>
                            <th>Found</th>
                            <th>Release info</th>
                            <th>Size</th>
                            <th>Torrent links</th>
                        </tr>
                        <?php
                            foreach ($filmsFound as $film) {
                        ?>
                        <tr class="<?php if($film->found) { echo 'success'; } ?>">
                            <td>
                                <a href="<?php echo LETTERBOXD_BASE_URL . $film->letterboxdSlug; ?>">
                                    <span class="glyphicon glyphicon-film" aria-hidden="true"></span>
                                    <?php echo $film->title . ($film->year ? ' (' . $film->year . ')' : ''); ?>
                                </a>
                            </td>
                            <td><?php echo $film->created; ?></td>
                            <td><?php echo $film->lastSearchDate; ?></td>
                            <td><?php echo $film->foundDate; ?></td>
                            <td>
                                <?php if ($film->found) : ?>
                                    <?php if ($film->torrentInfo) : ?>
                                        <a href="<?php echo $film->torrentInfo; ?>">
                                            <span class="glyphicon glyphicon-info-sign" aria-hidden="true"></span>
                                    <?php endif; ?>
                                            <?php echo $film->torrentTitle; ?>
                                    <?php if ($film->torrentInfo) : ?>
                                        </a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($film->found) : ?>
                                    <?php echo human_filesize($film->torrentSize); ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($film->found) : ?>
                                    <?php if ($film->torrentMagnet) : ?>
                                        <a href="<?php echo $film->torrentMagnet; ?>">
                                            <span class="glyphicon glyphicon-magnet" aria-hidden="true"></span>
                                            Magnet
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($film->torrentFile) : ?>
                                        <a href="<?php echo $film->torrentFile; ?>">
                                            <span class="glyphicon glyphicon-download-alt" aria-hidden="true"></span>
                                            File
                                        </a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php
                            }
                        ?>
                    </table>
                <?php endif; ?>
                </div><!-- .col-xs-12 -->
            </div><!-- .row -->
        </div><!-- .container-fluid -->
    </body>
</html>