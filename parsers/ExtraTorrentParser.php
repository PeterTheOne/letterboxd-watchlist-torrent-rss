<?php

include_once('TorrentSearchParserAbstract.php');

class ExtraTorrentParser extends TorrentSearchParserAbstract
{
    /* escape "%" with another "%", "%s" is where the title goes */
    public $searchURL = 'http://extratorrent.cc/rss.xml?type=search&cid=4&search=%s';

    public function getSearchURL($title) {
        return sprintf($this->searchURL, urlencode($title));
    }

    public function parseResults(\SimpleXMLElement $rss) {
        $results = array();
        foreach ($rss->channel->item as $torrentNode) {
            $torrent = new ArrayObject();
            $torrent->title = strtolower($torrentNode->children()->title);
            $torrent->seeds = $torrentNode->children()->seeders;
            $torrent->size = $torrentNode->children()->size;
            $torrent->torrentInfo = $torrentNode->children()->link;
            $torrent->torrentFile = $torrentNode->children()->enclosure->attributes()->{'url'};
            $results[] = $torrent;
        }
        return $results;
    }
}
