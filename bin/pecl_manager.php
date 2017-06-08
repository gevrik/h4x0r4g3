#!/usr/bin/env php
<?php
// NOTE: this script is provided as an example only. You will want to copy
// this to your application root so that the relative paths are correct

chdir(dirname(__DIR__));

// Setup autoloading
require 'init_autoloader.php';

// initialise the application (but don't run it)
$app = Zend\Mvc\Application::init(require 'config/application.config.php');

declare(ticks = 1);

$gm = $app->getServiceManager()->get('ZfGearmanPeclManager');
$gm->start();
