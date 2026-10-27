<?php

namespace MediumDubb\ConnectFour\Services;

use MediumDubb\ConnectFour\Domains\Board;
use MediumDubb\ConnectFour\Exceptions\ApiException;
use MediumDubb\ConnectFour\Exceptions\GameValidationException;
use MediumDubb\ConnectFour\Repositories\BoardRepo;

readonly class CreateRequestHandler
{
    public function __construct(private int $playerId){}

    /**
     * @throws GameValidationException
     */
    public static function validateRequest(string|int $playerId): self
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw new GameValidationException("Invalid request method", ["The request method must be POST"]);
        }

        $filteredPlayerID = filter_var($playerId, FILTER_VALIDATE_INT);

        if (
            $filteredPlayerID === false ||
            $filteredPlayerID <= 0
        )
            throw new GameValidationException( "Player assignment error", ["Please report to site admin"]);

        return new self (
            $playerId
        );
    }

    /**
     * @throws ApiException
     */
    public function createAndGetBoard(): Board
    {
        $newBoard = new BoardRepo()->create($this->playerId);
        return Board::fromDB($newBoard);
    }
}