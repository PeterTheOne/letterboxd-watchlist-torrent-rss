<?php

include_once('TorrentSearchParserAbstract.php');

class KickassTorrentsParser extends TorrentSearchParserAbstract
{
    /* escape "%" with another "%", "%s" is where the title goes */
    public $searchURL = 'https://kickass.unblocked.la/usearch/%s%%20category%%3Amovies/?rss=1';

    public function getSearchURL($searchTerms) {
        return sprintf($this->searchURL, rawurlencode($searchTerms));
    }

    public function parseResults(\SimpleXMLElement $rss) {
        $results = array();
        foreach ($rss->channel->item as $torrentNode) {
            $torrent = new ArrayObject();
            $torrent->title = $torrentNode->children()->title;
            $torrent->seeds = intval($torrentNode->children('http://xmlns.ezrss.it/0.1/')->seeds);
            $torrent->size = (float)$torrentNode->children('http://xmlns.ezrss.it/0.1/')->contentLength;
            $torrent->torrentInfo = $torrentNode->children()->link;
            $torrent->torrentInfoHash = $torrentNode->children('http://xmlns.ezrss.it/0.1/')->infoHash;
            $torrent->torrentFile = $torrentNode->children()->enclosure->attributes()->{'url'};
            $torrent->torrentMagnet = $torrentNode->children('http://xmlns.ezrss.it/0.1/')->magnetURI;
            if ($torrent->torrentFile || $torrent->torrentMagnet)
                $results[] = $torrent;
        }
        return $results;
    }
}
