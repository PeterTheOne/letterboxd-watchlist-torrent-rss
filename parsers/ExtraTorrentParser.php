<?php

include_once('TorrentSearchParserAbstract.php');

class ExtraTorrentParser extends TorrentSearchParserAbstract
{
    /* escape "%" with another "%", "%s" is where the title goes */
    public $searchURL = 'http://extratorrent.cc/rss.xml?type=search&cid=4&search=%s';

    public function getSearchURL($searchTerms) {
        return sprintf($this->searchURL, rawurlencode($searchTerms));
    }

    public function parseResults(\SimpleXMLElement $rss) {
        $results = array();
        foreach ($rss->channel->item as $torrentNode) {
            $torrent = new ArrayObject();
            $torrent->title = $torrentNode->children()->title;
            $torrent->seeds = $torrentNode->children()->seeders;
            $torrent->size = $torrentNode->children()->size;
            $torrent->torrentInfo = $torrentNode->children()->link;
            $torrent->torrentInfoHash = $torrentNode->children()->info_hash;
            $torrent->torrentFile = ($torrentNode->children()->enclosure && $torrentNode->children()->enclosure->attributes()) ? $torrentNode->children()->enclosure->attributes()->{'url'} : '';
            $torrent->torrentMagnet = '';
            if ($torrent->torrentFile || $torrent->torrentMagnet)
                $results[] = $torrent;
        }
        return $results;
    }
}
