<?php

namespace MediumDubb\ConnectFour\Services;

use MediumDubb\ConnectFour\Domains\Board;
use MediumDubb\ConnectFour\Exceptions\ApiException;
use MediumDubb\ConnectFour\Exceptions\GameValidationException;
use MediumDubb\ConnectFour\Repositories\BoardRepo;

class JoinRequestHandler
{
    private const string API_BOARD_ID_PARAM = 'boardId';

    public function __construct(private readonly int $boardId, private readonly int $playerId){}

    /**
     * @throws GameValidationException
     */
    public static function validateRequest(string|int $playerId): self
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET')
            throw new GameValidationException( "Invalid request method", ["The request method must be GET"] );

        $boardID = $_GET[self::API_BOARD_ID_PARAM] ?? null;
        $filteredPlayerID = filter_var($playerId, FILTER_VALIDATE_INT);

        if (is_null($boardID))
            throw new GameValidationException("Invalid board request", ["Missing parameter `" . self::API_BOARD_ID_PARAM . "` from request"]);

        if (
            $filteredPlayerID === false ||
            $filteredPlayerID <= 0
        )
            throw new GameValidationException( "Player assignment error", ["Please report to site admin"]);

        $filteredBoardID = filter_var($boardID, FILTER_VALIDATE_INT);

        if (
            $filteredBoardID === false ||
            $filteredBoardID <= 0
        )
            throw new GameValidationException( "Invalid board request", ["Parameter `" . self::API_BOARD_ID_PARAM . "` must be integer greater than 0"]);

        return new self (
            $filteredBoardID,
            $playerId
        );
    }

    /**
     * @throws ApiException
     */
    public function joinAndGetBoard(): Board
    {
        $updatedRow = new BoardRepo()->join($this->boardId, $this->playerId);
        return Board::fromDB($updatedRow);
    }
}