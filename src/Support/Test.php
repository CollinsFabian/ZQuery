<?php

use ZQuery\Support\ConfigLoader;
use ZQuery\Support\DatabaseChecker;

$config = new ConfigLoader(__DIR__ . '/../../config/config.php');
$pdo = new PDO($config->get('dsn'), $config->get('user'), $config->get('password'));

$dbCheck = new DatabaseChecker($pdo);
if (!$dbCheck->ping()) {
    die("Database is down!");
}
