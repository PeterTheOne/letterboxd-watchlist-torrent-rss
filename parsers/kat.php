<?php
class KickassTorrents
{
    public $searchURL = 'https://kat.cr/usearch/category:movies%%20%s/?rss=1';

    public function getSearchURL($title) {
        return sprintf($this->searchURL, rawurlencode($title));
    }

    public function parseResults($rss) {
        $results = array();
        foreach ($rss->channel->item as $torrentNode) {
            $torrent = new ArrayObject();
            $torrent->title = strtolower($torrentNode->children()->title);
            $torrent->seeds = $torrentNode->children('http://xmlns.ezrss.it/0.1/')->seeds;
            $torrent->size = $torrentNode->children('http://xmlns.ezrss.it/0.1/')->contentLength;
            $torrent->torrentLink = $torrentNode->children()->enclosure->attributes()->{'url'};
            $torrent->torrentUrl = $torrentNode->children('http://xmlns.ezrss.it/0.1/')->magnetURI;
            $results[] = $torrent;
        }
        return $results;
    }
}
