<?php

use Zend\Mvc\Application;

/**
 * This makes our life easier when dealing with paths. Everything is relative
 * to the application root now.
 */
chdir(dirname(__DIR__));

/**
 * Display all errors when APPLICATION_ENV is development.
 */
//if ('development' === $_SERVER['APPLICATION_ENV']) {
    error_reporting(E_ALL);
    ini_set("display_errors", 1);
//}

//error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE);

// Decline static file requests back to the PHP built-in webserver
if (php_sapi_name() === 'cli-server') {
    $path = realpath(__DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
    if (__FILE__ !== $path && is_file($path)) {
        return false;
    }
    unset($path);
}

// Composer autoloading
include __DIR__ . '/../vendor/autoload.php';

if (! class_exists(Application::class)) {
    throw new RuntimeException(
        "Unable to load application.\n"
        . "- Type `composer install` if you are developing locally.\n"
    );
}

// Run the application!
Application::init(require __DIR__ . '/../config/application.config.php')->run();
