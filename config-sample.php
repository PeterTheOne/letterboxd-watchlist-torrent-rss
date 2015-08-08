<?php

define('ENVIRONMENT', 'production');

if (ENVIRONMENT === 'development') {
    error_reporting(E_ALL | E_STRICT);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ERROR | E_PARSE);
}

set_time_limit(600); /* 5min. max execute time */

define('POORMANSCRON', false); /* index.php will run cron.php on load */

define('SQLITE_FILENAME', 'database.sqlite3');

define('LETTERBOXD_USERNAME', ''); /* write your username here */
define('LETTERBOXD_BASE_URL', 'http://letterboxd.com');
define('LETTERBOXD_URL', LETTERBOXD_BASE_URL . '/' . LETTERBOXD_USERNAME . '/watchlist/');

define('MAX_WATCHLIST_PAGES_TO_FETCH', 3);

define('LIMIT_FIND_NOT_SEARCHED_YET', 10);
define('LIMIT_FIND_NOT_FOUND_YET', 10);

define('MINIMUM_SEEDS', 4);

define('MINIMUM_FILESIZE', 3); /* in GB */
define('MAXIMUM_FILESIZE', 19); /* in GB */

$sites = array( /* prioritize sites to search */
    'extratorrent',
    'kickasstorrents'
);

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

include_once('DatabaseAbstract.php');
include_once('SqliteDatabase.php');

$pdo = new \PDO('sqlite:' . SQLITE_FILENAME);
$database = new LetterBoxdWatchlistRss\SqliteDatabase($pdo);
