<?php

include_once('TorrentSearchParserAbstract.php');

class ExtraTorrentParser extends TorrentSearchParserAbstract
{
    /* escape "%" with another "%", "%s" is where the title goes */
    public $searchURL = 'http://extratorrent.cc/rss.xml?type=search&cid=4&search=%s%%20%s';

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
            $torrent->title = $torrentNode->children()->title;
            $torrent->seeds = $torrentNode->children()->seeders;
            $torrent->size = $torrentNode->children()->size;
            $torrent->torrentInfo = $torrentNode->children()->link;
            $torrent->torrentInfoHash = $torrentNode->children()->info_hash;
            $torrent->torrentFile = ($torrentNode->children()->enclosure && $torrentNode->children()->enclosure->attributes()) ? $torrentNode->children()->enclosure->attributes()->{'url'} : '';
            $torrent->torrentMagnet = ($torrent->torrentInfoHash) ? 'magnet:?xt=urn:btih:' . $torrent->torrentInfoHash : '';
            if ($torrent->torrentFile || $torrent->torrentMagnet)
                $results[] = $torrent;
        }
        return $results;
    }
}
