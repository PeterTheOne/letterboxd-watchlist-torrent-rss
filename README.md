letterboxd-watchlist-torrent-rss
================================

is a webapp that crawls the [letterboxd](http://letterboxd.com/) watchlist of a certain user and searches torrent sites 
like KickassTorrents for torrents that match the watchlist and serves them as a rss feed for automatic download.

features
--------

- support for multiple torrent sites
- filter: black- and whitelist, filesize, min. seeds
- supports mutliple torrent clients:
- - deluged with YaRSS2
- - ...
- file based sqlite database
- optional poormanscron

how to use
----------

- you need a webserver with php to run this.
- download or git clone the repository to your sever.
- you may need to make the folder writable to your server user.
- copy `config-sample.php` to `config.php`.
- add your letterboxd username to the config file.
- open `cron.php`, wait and then `status.php` to check if it is working.
- schedule a [cronjob](https://en.wikipedia.org/wiki/Cron) to call `cron.php` every hour/day or enable `POORMANSCRON` in the config.
- setup your torrent client to call `index.php`.
