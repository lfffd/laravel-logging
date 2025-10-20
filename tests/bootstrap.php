<?php

/*
 * Bootstrap file for PHPUnit tests
 */

// Require composer autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Set timezone
date_default_timezone_set('UTC');