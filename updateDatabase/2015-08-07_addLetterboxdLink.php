<?php

error_reporting(E_ERROR | E_PARSE);

try {
    $pdo = new PDO('sqlite:../database.sqlite3');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);

    $pdo->query('
      ALTER TABLE films ADD COLUMN letterboxdSlug TEXT;
    ');
} catch (Exception $exception) {
    echo $exception->getMessage();
}

echo 'updated!';