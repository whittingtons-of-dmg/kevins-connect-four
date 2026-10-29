<?php

declare(strict_types=1);

namespace MediumDubb\ConnectFour\Exceptions;

class GameConflictException extends ApiException
{
    public function __construct(
        string $message = 'A conflict has been encountered',
        private readonly array $errors = []
    ) {
        parent::__construct($message, 409);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}