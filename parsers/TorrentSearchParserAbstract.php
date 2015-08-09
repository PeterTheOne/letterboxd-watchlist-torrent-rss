<?php

abstract class TorrentSearchParserAbstract
{

    /**
     * @param $title
     * @param $year
     * @return mixed
     */
    abstract public function getSearchURL($title, $year);

    /**
     * @param SimpleXMLElement $rss
     * @return array
     */
    abstract public function parseResults(\SimpleXMLElement $rss);
}
