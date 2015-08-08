<?php
class ExtraTorrent
{
    public $searchURL = 'http://extratorrent.cc/rss.xml?type=search&cid=4&search=%s';

    public function getSearchURL($title) {
        return sprintf($this->searchURL, urlencode($title));
    }

    public function parseResults($rss) {
        $results = array();
        foreach ($rss->channel->item as $torrentNode) {
            $torrent = new ArrayObject();
            $torrent->title = strtolower($torrentNode->children()->title);
            $torrent->seeds = $torrentNode->children()->seeders;
            $torrent->size = $torrentNode->children()->size;
            $torrent->torrentLink = $torrentNode->children()->link;
            $torrent->torrentUrl = $torrentUrl = $torrentNode->children()->enclosure->attributes()->{'url'};
            $results[] = $torrent;
        }
        return $results;
    }
}
