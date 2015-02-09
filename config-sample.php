<?php

error_reporting(E_ERROR | E_PARSE);
set_time_limit(600); /* 5min. max execute time */

define('POORMANSCRON', false); /* index.php will run cron.php on load */

define('SQLITE_FILENAME', 'database.sqlite3');

define('LETTERBOXD_USERNAME', ''); /* write your username here */
define('LETTERBOXD_URL', 'http://letterboxd.com/' . LETTERBOXD_USERNAME . '/watchlist/');

define('KICKASSTORRENT_URL','http://kickass.to/usearch/category:movies%20');

define('LIMIT_FIND_NOT_SEARCHED_YET', 10);
define('LIMIT_FIND_NOT_FOUND_YET', 10);

define('MINIMUM_SEEDS', 4);

$titleWhitelist = array( /* one of these must be in the title */
    '720p',
    '1080p',
    'bdrip',
    'brrip'
);

$titleBlacklist = array( /* none of these may be in the title */
    'upscaled',
    'hdcam',
    'trailer',
    'yify',
    'ganool'
);

$pdo = new PDO('sqlite:' . SQLITE_FILENAME);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);

$pdo->query('
    CREATE TABLE IF NOT EXISTS films (
        id INTEGER PRIMARY KEY,
        created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        foundDate TIMESTAMP,
        lastSearchDate TIMESTAMP,
        title VARCHAR(255),
        searched BOOLEAN DEFAULT 0,
        found BOOLEAN DEFAULT 0,
        torrent TEXT,
        torrentUrl TEXT,
        UNIQUE(title)
    );
');
