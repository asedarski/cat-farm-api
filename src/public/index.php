<?php
require '../../vendor/autoload.php';

// Require configuration settings
$settings = require __DIR__.'/settings.php';

$app = new \Slim\App(['settings' => $settings]);

// Require the dependencies
require __DIR__.'/dependencies.php';

// Register the routes
require __DIR__.'/routes.php';

$app->run();
