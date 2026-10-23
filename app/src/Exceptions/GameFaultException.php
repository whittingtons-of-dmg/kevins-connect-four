<?php

declare(strict_types=1);

namespace MediumDubb\ConnectFour\Exceptions;

use Throwable;

class GameFaultException extends ApiException
{
    public function __construct(
        string $message = 'A game process has encountered an error',
        private readonly array $errors = []
    ) {
        parent::__construct($message);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}