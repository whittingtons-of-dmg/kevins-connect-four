<?php

namespace MediumDubb\ConnectFour\Exceptions;

use Exception;
use Throwable;

class ApiException extends Exception
{
    public function __construct(string $message, private readonly int $statusCode = 500, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}