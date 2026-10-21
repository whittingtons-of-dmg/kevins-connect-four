<?php

use Dotenv\Dotenv;
use MediumDubb\ConnectFour\Controllers\GameApiController;
use MediumDubb\ConnectFour\Controllers\PageController;
use MediumDubb\ConnectFour\Core\AppRouter;

// Find autoload.php
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require __DIR__ . '/../vendor/autoload.php';
} elseif (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
} else {
    header('HTTP/1.1 500 Internal Server Error');
    echo "autoload.php not found";
    exit(1);
}

$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

$router = AppRouter::getRouter();

// Define your routes here
$router->get('/', [new PageController(), 'index']);
$router->get('/join-room', [new GameApiController(), 'join']);
$router->get('/get-board-state', [new GameApiController(), 'getBoardState']);
$router->get('/drop-token', [new GameApiController(), 'dropToken']);

$router->post('/create-room', [new GameApiController(), 'create']);

// Run the router using the server request data
$router->dispatch();