<?php

include_once('TorrentSearchParserAbstract.php');

class KickassTorrentsParser extends TorrentSearchParserAbstract
{
    /* escape "%" with another "%", "%s" is where the title goes */
    public $searchURL = 'https://kat.cr/usearch/%s%%20%s%%20category%%3Amovies/?rss=1';

    public function getSearchURL($title, $year) {
        // Remove all characters except A-Z, a-z, 0-9, dots, hyphens and spaces
        // Note that the hyphen must go last not to be confused with a range (A-Z)
        // and the dot, being special, is escaped with \
        $title = preg_replace('/[^A-Za-z0-9\. -]/', '', $title);
        
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
