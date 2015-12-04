<?php
ini_set('xdebug.max_nesting_level', 1000);
set_time_limit(0);
date_default_timezone_set('Europe/Berlin');

// include the composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// import the Symfony Console Application

use Symfony\Component\Console\Application;
use SiteChecker\Commands\CheckCommand;

$app = new Application();
$app->add(new CheckCommand());
$app->run();
