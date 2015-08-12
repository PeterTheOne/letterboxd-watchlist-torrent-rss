<?php

namespace LetterBoxdWatchlistRss;

abstract class DatabaseAbstract {

    /**
     * @var $pdo \PDO
     */
    protected $pdo;

    /**
     * @param $pdo \PDO
     */
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_OBJ);

        $this->setupDatabase();
        $this->updateDatabase();
    }

    /**
     * @return mixed
     */
    abstract protected function setupDatabase();

    /**
     * @return mixed
     */
    abstract protected function updateDatabase();

    /**
     * @return mixed
     */
    abstract public function getFoundFilmsOrderByFoundDate();

    /**
     * @return mixed
     */
    abstract public function getFilmsOrderByCreated();

    /**
     * @param $limit
     * @return mixed
     */
    abstract public function getFilmTitlesNotSearchedLimit($limit);

    /**
     * @param $limit
     * @return mixed
     */
    abstract public function getFilmTitlesSearchedNotFoundLimit($limit);

    /**
     * @param $title
     * @return mixed
     */
    abstract public function addOrIgnoreTitle($title);

    /**
     * @param $title
     * @param $letterboxdSlug
     * @return mixed
     */
    abstract public function changeLetterboxdSlug($title, $letterboxdSlug);

    /**
     * @param $titleList
     * @return mixed
     */
    abstract public function removeFilmsNotInTitleList($titleList);

    /**
     * @param $title
     * @return mixed
     */
    abstract public function setSearched($title);

    /**
     * @param $title
     * @param $torrentInfo
     * @param $torrentMagnet
     * @param $torrentFile
     * @param $torrentSize
     * @return mixed
     */
    abstract public function setFound($title, $torrentInfo, $torrentMagnet, $torrentFile, $torrentSize);

    /**
     * @return mixed
     */
    abstract public function getFilmsWithoutYearNotFound();

    /**
     * @param $title
     * @param $year
     * @return mixed
     */
    abstract public function changeYear($title, $year);

}