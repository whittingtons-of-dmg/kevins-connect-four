<?php

namespace MediumDubb\ConnectFour;

use MediumDubb\ConnectFour\Exceptions\ApiException;
use MediumDubb\ConnectFour\Exceptions\GameConflictException;
use MediumDubb\ConnectFour\Exceptions\GameFaultException;
use MediumDubb\ConnectFour\Exceptions\GameValidationException;
use MediumDubb\ConnectFour\Exceptions\NotFoundException;
use Psr\Log\LoggerInterface;
use Throwable;

readonly class GameExceptionHandler
{
    public function __construct(private LoggerInterface $logger){}

    public function handle(Throwable $exception): void
    {
        $status = 500;

        $response = [
            'code' => $status,
            'error' => [
                'message' => 'An unexpected error has occurred',
            ]
        ];

        if ($exception instanceof ApiException) {
            $status = $exception->getStatusCode();
            $response['code'] = $status;
            $response['error']['message'] = $exception->getMessage();

            if ($exception instanceof GameValidationException ||
                $exception instanceof GameConflictException ||
                $exception instanceof NotFoundException ||
                $exception instanceof GameFaultException) {
                $status = $exception->getStatusCode();
                $response['code'] = $status;
                $response['error']['message'] = $exception->getMessage();
                if (!empty($exception->getErrors())) {
                    $response['error']['details'] = $exception->getErrors();
                }
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