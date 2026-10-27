<?php

namespace MediumDubb\ConnectFour\Services;

use MediumDubb\ConnectFour\Domains\Board;
use MediumDubb\ConnectFour\Exceptions\ApiException;
use MediumDubb\ConnectFour\Exceptions\GameValidationException;
use MediumDubb\ConnectFour\Exceptions\NotFoundException;
use MediumDubb\ConnectFour\PDOConnector;
use MediumDubb\ConnectFour\Repositories\BoardRepo;

readonly class GetterRequestHandler
{
    private const string API_BOARD_ID_PARAM = 'boardId';

    private PDOConnector $db;

    public function __construct(private int $boardId)
    {
        $this->db = new PDOConnector();
    }

    /**
     * @throws GameValidationException
     */
    public static function validateRequest(): self
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            throw new GameValidationException( "Invalid request method", ["The request method must be GET"] );
        }

        $boardID = $_GET[self::API_BOARD_ID_PARAM] ?? null;

        if (is_null($boardID))
            throw new GameValidationException("Invalid board request", ["Missing parameter `" . self::API_BOARD_ID_PARAM ."` from request"]);

        $filteredBoardID = filter_var($boardID, FILTER_VALIDATE_INT);

        if (
            $filteredBoardID === false ||
            $filteredBoardID <= 0
        )
            throw new GameValidationException( "Invalid board request", ["Board ID must be integer greater than 0"]);

            return new self($filteredBoardID);
    }

    /**
     * @throws ApiException
     */
    public function getTheBoard(): Board
    {
        $boardRow = new BoardRepo($this->db)->getBoardByID($this->boardId);

        if (!is_array($boardRow))
            throw new NotFoundException( "The requested board was not found");
        else
            return Board::fromDB($boardRow);
    }
}