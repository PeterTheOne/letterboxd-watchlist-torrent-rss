letterboxd-watchlist-torrent-rss
================================

is a webapp that crawls the [letterboxd](http://letterboxd.com/) watchlist of a certain user and searches torrent sites 
like KickassTorrents for torrents that match the watchlist and serves them as a rss feed for automatic download.

features
--------

- support for multiple torrent sites
- filter: black- and whitelist, filesize, min. seeds
- supports multiple torrent clients:
- - deluged with YaRSS2
- - Synology Download Center
- - ...
- file based sqlite database
- optional poormanscron

requirements
------------
- a webserver with php support
- letterboxd account
- bittorrent client with RSS support
- (optional) cron

how to install
--------------

- download or git clone the repository to your sever.
- you may need to make the folder writable to your server user.
- copy `config-sample.php` to `config.php`.
- add your letterboxd username to the config file.
- open `cron.php`, wait and then `status.php` to check if it is working.
- schedule a [cronjob](https://en.wikipedia.org/wiki/Cron) to call `cron.php` every hour/day or enable `POORMANSCRON` in the config.
- setup your torrent client to call `index.php`.

how to update
-------------

- git clone or copy new files to server.
- copy `config-sample.php` changes to `config.php`.
- open `cron.php`, wait
- then `status.php` to check if it is working.

debug
-----

if something doesn't seem to work enable development mode in `config.php` to see error messages.
