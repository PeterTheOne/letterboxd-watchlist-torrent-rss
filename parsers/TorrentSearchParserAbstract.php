<?php

abstract class TorrentSearchParserAbstract
{

    /**
     * @param $searchTerms
     * @return mixed
     */
    abstract public function getSearchURL($searchTerms);

    /**
     * @param SimpleXMLElement $rss
     * @return array
     */
    abstract public function parseResults(\SimpleXMLElement $rss);
}
