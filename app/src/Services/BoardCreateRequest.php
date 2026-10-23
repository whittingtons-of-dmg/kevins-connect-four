<?php

namespace MediumDubb\ConnectFour\Services;

use MediumDubb\ConnectFour\Exceptions\ApiException;

final readonly class BoardCreateRequest
{
    public function __construct(
        private int $playerId,
    ){}

    /**
     * @throws ApiException
     */
    public static function validateRequestMethod(int|string $playerId): self
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw new ApiException("Invalid request method", 405);
        }
        if (
            $playerId === '' ||
            $playerId === '0' ||
            !filter_var($playerId, FILTER_VALIDATE_INT)
        ) {
            throw new ApiException("Invalid creation request", 400);
        }

        return new self(
            playerId: $playerId
        );
    }

    public function getPlayerID(): int
    {
        return $this->playerId;
    }
}