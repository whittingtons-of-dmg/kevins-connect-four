<?php

namespace MediumDubb\ConnectFour\Services;

use MediumDubb\ConnectFour\Domains\Board;
use MediumDubb\ConnectFour\Exceptions\ApiException;
use MediumDubb\ConnectFour\Exceptions\GameValidationException;
use MediumDubb\ConnectFour\Repositories\BoardRepo;

class BoardRequestHandler
{
    private const string BOARD_PARAM = 'boardId';

    /**
     * @throws ApiException
     */
    public static function boardFromJoinRequest(int $playerID): Board
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET')
            throw new GameValidationException( "Invalid request method", ["The request method must be GET"] );

        $boardID = $_GET[self::BOARD_PARAM] ?? null;

        if (is_null($boardID))
            throw new GameValidationException("Invalid board request", ["Missing parameter 'boardId' from request"]);

        $filteredBoardID = filter_var($boardID, FILTER_VALIDATE_INT);

        if (
            $filteredBoardID !== false &&
            $filteredBoardID > 0
        ) {
            $updatedRow = new BoardRepo()->join($boardID, $playerID);
            return Board::fromDB($updatedRow);
        }
        else
            throw new GameValidationException( "Invalid board request", ["Board ID must be integer greater than 0"]);
    }

    /**
     * @throws GameValidationException
     * @throws ApiException
     */
    public static function boardFromCreateRequest(int|string $playerId): Board
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw new GameValidationException("Invalid request method", ["The request method must be POST"]);
        }

        $filteredPlayerID = filter_var($playerId, FILTER_VALIDATE_INT);

        if (
            $filteredPlayerID !== false &&
            $filteredPlayerID > 0
        ) {
            $newBoard = new BoardRepo()->create($filteredPlayerID);
            return Board::fromDB($newBoard);
        } else
            throw new GameValidationException("Invalid creation request", ["Player ID must be integer greater than 0"]);
    }

    /**
     * @throws GameValidationException
     * @throws ApiException
     */
    public static function boardFromGetterRequest(): Board
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            throw new GameValidationException( "Invalid request method", ["The request method must be GET"] );
        }

        $boardID = $_GET[self::BOARD_PARAM] ?? null;

        if (is_null($boardID))
            throw new GameValidationException("Invalid board request", ["Missing parameter 'boardId' from request"]);

        $filteredBoardID = filter_var($boardID, FILTER_VALIDATE_INT);

        if (
            $filteredBoardID !== false &&
            $filteredBoardID > 0
        ) {
            $boardRow = new BoardRepo()->getBoardByID($filteredBoardID);
            return Board::fromDB($boardRow);
        } else
            throw new GameValidationException( "Invalid board request", ["Board ID must be integer greater than 0"]);
    }
}