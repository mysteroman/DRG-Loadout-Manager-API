<?php

if (!@require __DIR__ . '/../vendor/autoload.php') {
    die('You must set up the project dependencies, run composer install');
}

define('ROOT_DIR', __DIR__);

// Adds any other specifics for the test environnement
