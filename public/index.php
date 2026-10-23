<?php

use Dotenv\Dotenv;
use MediumDubb\ConnectFour\AppRouter;
use MediumDubb\ConnectFour\GameApiController;
use MediumDubb\ConnectFour\GameExceptionHandler;
use Monolog\Logger;

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

$exceptionHandler = new GameExceptionHandler(new Logger('APIErrors'));

set_exception_handler([$exceptionHandler, 'handle']);

$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

$router = AppRouter::getRouter();
$router->get('/join-room', [new GameApiController(), 'join']);
$router->get('/get-board-state', [new GameApiController(), 'getBoardState']);
$router->get('/drop-token', [new GameApiController(), 'dropToken']);
$router->post('/create-room', [new GameApiController(), 'create']);

// Run the router using the server request data
try {
    $router->dispatch();
} catch (Throwable $e) {
    $exceptionHandler->handle($e);
}
