<?php

declare(strict_types=1);

namespace MediumDubb\ConnectFour\Exceptions;

class NotFoundException extends ApiException
{
    public function __construct(
        string $message = 'Resource not found',
        private readonly array $errors = []
    ) {
        parent::__construct($message, 422);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}