<?php

namespace MediumDubb\ConnectFour;

use MediumDubb\ConnectFour\Exceptions\ApiException;
use MediumDubb\ConnectFour\Exceptions\GameValidationException;
use Psr\Log\LoggerInterface;
use Throwable;

class GameExceptionHandler
{
    public function __construct(private readonly LoggerInterface $logger){}

    public function handle(Throwable $exception): void
    {
        $status = 500;

        $response = [
            'error' => [
                'message' => 'An unexpected error has occurred',
            ]
        ];

        if ($exception instanceof ApiException) {
            $status = $exception->getStatusCode();

            $response['error']['message'] = $exception->getMessage();

            if ($exception instanceof GameValidationException) {
                $status = $exception->getStatusCode();

                $response['error']['message'] = $exception->getMessage();
            }
        }

        if ($status >= 500) {
            $this->logger->error('Unhandled API exception', ['exception' => $exception]);
        }

        if (!headers_sent()) {
            header('Content-Type: application/json');
            header('Cache-Control: no-store');
        }

        echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    }
}