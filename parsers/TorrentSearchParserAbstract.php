<?php

abstract class TorrentSearchParserAbstract
{

    /**
     * @param string $title
     * @return string
     */
    abstract public function getSearchURL($title);

    /**
     * @param SimpleXMLElement $rss
     * @return array
     */
    abstract public function parseResults(\SimpleXMLElement $rss);
}
