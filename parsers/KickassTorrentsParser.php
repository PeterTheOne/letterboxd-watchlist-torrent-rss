<?php

include_once('TorrentSearchParserAbstract.php');

class KickassTorrentsParser extends TorrentSearchParserAbstract
{
    /* escape "%" with another "%", "%s" is where the title goes */
    public $searchURL = 'https://kat.cr/usearch/category:movies%%20%s%%20%s/?rss=1';

    public function getSearchURL($title, $year) {
        return sprintf($this->searchURL, rawurlencode($title), rawurlencode($year));
    }

    public function parseResults(\SimpleXMLElement $rss) {
        $results = array();
        foreach ($rss->channel->item as $torrentNode) {
            $torrent = new ArrayObject();
            $torrent->title = strtolower($torrentNode->children()->title);
            $torrent->seeds = $torrentNode->children('http://xmlns.ezrss.it/0.1/')->seeds;
            $torrent->size = $torrentNode->children('http://xmlns.ezrss.it/0.1/')->contentLength;
            $torrent->torrentInfo = $torrentNode->children()->link;
            $torrent->torrentFile = $torrentNode->children()->enclosure->attributes()->{'url'};
            $torrent->torrentMagnet = $torrentNode->children('http://xmlns.ezrss.it/0.1/')->magnetURI;
            $results[] = $torrent;
        }
        return $results;
    }
}
