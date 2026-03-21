<?php

use ZQuery\Support\ConfigLoader;
use ZQuery\Support\DatabaseChecker;
use ZQuery\Support\Environment;

Environment::load(__DIR__ . '/../../config/.env');

$config = new ConfigLoader;

$db = new PDO($config::get('DB_PDO_DSN'), $config::get('DB_USER'), $config::get('DB_PASSWORD'));
// $db = new mysqli($config::get('DB_HOST'), $config::get('DB_USER'), $config::get('DB_PASSWORD'), $config::get('DB_NAME'), $config::get('DB_PORT'));

$dbCheck = new DatabaseChecker($db);
if (!$dbCheck->ping()) die("Database is down!");
