<?php

declare(strict_types=1);

namespace MediumDubb\ConnectFour\Exceptions;

use Throwable;

class GameValidationException extends ApiException
{
    public function __construct(
        string $message = 'Game validation failed',
        private readonly array $errors = []
    ) {
        parent::__construct($message, 422);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}