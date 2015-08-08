<?php

namespace LetterBoxdWatchlistRss;

class SqliteDatabase extends DatabaseAbstract {

    /**
     * @var \PDOStatement $addOrIgnoreTitleStatement
     */
    private $addOrIgnoreTitleStatement = null;

    /**
     * @var \PDOStatement $updateLetterboxdSlugStatement
     */
    private $changeLetterboxdSlugStatement = null;

    /**
     * @var \PDOStatement $setSearchedStatement
     */
    private $setSearchedStatement = null;

    /**
     * @var \PDOStatement $setFoundStatement
     */
    private $setFoundStatement = null;

    protected function setupDatabase() {
        try {
            $query = $this->pdo->query('
                CREATE TABLE IF NOT EXISTS films (
                    id INTEGER PRIMARY KEY,
                    created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    foundDate TIMESTAMP,
                    lastSearchDate TIMESTAMP,
                    title VARCHAR(255),
                    year  VARCHAR(255),
                    letterboxdSlug TEXT,
                    searched BOOLEAN DEFAULT 0,
                    found BOOLEAN DEFAULT 0,
                    torrent TEXT,
                    torrentUrl TEXT,
                    UNIQUE(title)
                );
            ');
        } catch (\PDOException $exception) {
            throw new \Exception('Could not setup database.');
        }
    }

    protected function updateDatabase() {
        try {
            $query = $this->pdo->query('
                PRAGMA table_info(films);
            ');
            $tableInfo = $query->fetchAll();
        } catch (\PDOException $exception) {
            throw new \Exception('Could not get table info.');
        }

        try {
            $filterFoundDate = array_filter($tableInfo, function($var) {
                return $var->name === 'foundDate';
            });

            if (empty($filterFoundDate)) {
                $this->pdo->query('
                  ALTER TABLE films ADD COLUMN foundDate TIMESTAMP;
                ');

                $this->pdo->query('
                    UPDATE films
                        SET
                            foundDate = created;
                ');
            }
        } catch (\PDOException $exception) {
            throw new \Exception('Could not update table (add foundDate).');
        }

        try {
            $filterLastSearchDate = array_filter($tableInfo, function($var) {
                return $var->name === 'lastSearchDate';
            });

            if (empty($filterLastSearchDate)) {
                $this->pdo->query('
                    ALTER TABLE films ADD COLUMN lastSearchDate TIMESTAMP;
                ');

                $this->pdo->query('
                    UPDATE films
                        SET
                            lastSearchDate = foundDate;
                ');
            }
        } catch (\PDOException $exception) {
            throw new \Exception('Could not update table (add lastSearchDate).');
        }

        try {
            $filterLetterboxdSlug = array_filter($tableInfo, function($var) {
                return $var->name === 'letterboxdSlug';
            });

            if (empty($filterLetterboxdSlug)) {
                $this->pdo->query('
                    ALTER TABLE films ADD COLUMN letterboxdSlug TEXT;
                ');
            }
        } catch (\PDOException $exception) {
            throw new \Exception('Could not update table (add letterboxdSlug).');
        }

        try {
            $filterLetterboxdSlug = array_filter($tableInfo, function($var) {
                return $var->name === 'letterboxdSlug';
            });

            if (empty($filterLetterboxdSlug)) {
                $this->pdo->query('
                    ALTER TABLE films ADD COLUMN letterboxdSlug TEXT;
                ');
            }
        } catch (\PDOException $exception) {
            throw new \Exception('Could not update table (add letterboxdSlug).');
        }

        try {
            $filterYear = array_filter($tableInfo, function($var) {
                return $var->name === 'year';
            });

            if (empty($filterYear)) {
                $this->pdo->query('
                    ALTER TABLE films ADD COLUMN year VARCHAR(255);
                ');
            }
        } catch (\PDOException $exception) {
            throw new \Exception('Could not update table (add year).');
        }
    }

    public function getFoundFilmsOrderByFoundDate() {
        try {
            $filmsFoundQuery = $this->pdo->query('SELECT foundDate, title, year, torrent, torrentUrl FROM films WHERE found = 1 ORDER BY foundDate;');
            return $filmsFoundQuery->fetchAll();
        } catch (\PDOException $exception) {
            throw new \Exception('Could not get found films.');
        }
    }

    public function getFilmsOrderByCreated() {
        try {
            $filmsFoundQuery = $this->pdo->query('SELECT title, year, letterboxdSlug, created, lastSearchDate, foundDate, torrent, torrentUrl FROM films ORDER BY created;');
            return $filmsFoundQuery->fetchAll();
        } catch (\PDOException $exception) {
            throw new \Exception('Could not get films.');
        }
    }

    public function getFilmTitlesNotSearchedLimit($limit) {
        try {
            $filmsNotSearchedQuery = $this->pdo->query('SELECT title FROM films WHERE searched = 0 ORDER BY created LIMIT ' . $limit . ';');
            return $filmsNotSearchedQuery->fetchAll();
        } catch (\PDOException $exception) {
            throw new \Exception('Could not get film titles not searched.');
        }
    }

    public function getFilmTitlesSearchedNotFoundLimit($limit) {
        try {
            $filmsNotFoundQuery = $this->pdo->query('SELECT title FROM films WHERE searched = 1 AND found = 0 ORDER BY lastSearchDate LIMIT ' . $limit . ';');
            return $filmsNotFoundQuery->fetchAll();
        } catch (\PDOException $exception) {
            throw new \Exception('Could not get film titles searched, not found.');
        }
    }

    public function addOrIgnoreTitle($title) {
        try {
            if (!$this->addOrIgnoreTitleStatement) {
                $this->addOrIgnoreTitleStatement = $this->pdo->prepare('
                    INSERT OR IGNORE INTO films (
                        title
                    ) VALUES (
                        :title
                    );
                ');
            }

            $this->addOrIgnoreTitleStatement->bindParam(':title', $title);
            $this->addOrIgnoreTitleStatement->execute();
        } catch (\PDOException $exception) {
            throw new \Exception('Could not add or ignore title.');
        }
    }

    public function changeLetterboxdSlug($title, $letterboxdSlug) {
        try {
            if (!$this->changeLetterboxdSlugStatement) {
                $this->changeLetterboxdSlugStatement = $this->pdo->prepare('
                    UPDATE films
                        SET
                            letterboxdSlug = :letterboxdSlug
                        WHERE
                            title = :title;
                ');
            }

            $this->changeLetterboxdSlugStatement->bindParam(':title', $title);
            $this->changeLetterboxdSlugStatement->bindParam(':letterboxdSlug', $letterboxdSlug);
            $this->changeLetterboxdSlugStatement->execute();
        } catch (\PDOException $exception) {
            throw new \Exception('Could not update letterboxdSlug.');
        }
    }

    public function removeFilmsNotInTitleList($titleList) {
        try {
            /* sanity check */
            if ($titleList < 10) {
                return;
            }
            $this->pdo->query('DELETE FROM films WHERE title NOT IN ("' . implode('", "', $titleList) . '")');
        } catch (\PDOException $exception) {
            throw new \Exception('Could not remove films not in title list.');
        }
    }

    public function setSearched($title) {
        try {
            if (!$this->setSearchedStatement) {
                $this->setSearchedStatement = $this->pdo->prepare('
                    UPDATE films SET searched = 1, lastSearchDate = datetime(\'now\', \'localtime\') WHERE title = :title;'
                );
            }

            $this->setSearchedStatement->bindParam(':title', $title);
            $this->setSearchedStatement->execute();
        } catch (\PDOException $exception) {
            throw new \Exception('Could not set searched.');
        }
    }

    public function setFound($title, $torrentLink, $torrentUrl) {
        try {
            if (!$this->setFoundStatement) {
                $this->setFoundStatement = $this->pdo->prepare('
                    UPDATE films
                        SET
                            foundDate = datetime(\'now\', \'localtime\'),
                            lastSearchDate = datetime(\'now\', \'localtime\'),
                            searched = 1,
                            found = 1,
                            torrent = :torrent,
                            torrentUrl = :torrentUrl
                        WHERE
                            title = :title;
                ');
            }

            $this->setFoundStatement->bindParam(':torrent', $torrentLink);
            $this->setFoundStatement->bindParam(':torrentUrl', $torrentUrl);
            $this->setFoundStatement->bindParam(':title', $title);
            $this->setFoundStatement->execute();
        } catch (\PDOException $exception) {
            throw new \Exception('Could not set found.');
        }
    }

}