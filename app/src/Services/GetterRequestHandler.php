<?php

namespace MediumDubb\ConnectFour\Services;

use Dotenv\Exception\ValidationException;
use MediumDubb\ConnectFour\Domains\Board;
use MediumDubb\ConnectFour\Exceptions\ApiException;
use MediumDubb\ConnectFour\Exceptions\GameValidationException;
use MediumDubb\ConnectFour\PDOConnector;
use MediumDubb\ConnectFour\Repositories\BoardRepo;

readonly class GetterRequestHandler
{
    private const string API_BOARD_ID_PARAM = 'boardId';

    private BoardRepo $boardRepo;

    public function __construct(private PDOConnector $db, private int $boardId)
    {
        $this->boardRepo = new BoardRepo($this->db);
    }

    /**
     * @throws GameValidationException
     */
    public static function buildRequestHandler(PDOConnector $db): self
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            throw new GameValidationException( "Invalid request method", ["The request method must be GET"] );
        }

        $boardID = $_GET[self::API_BOARD_ID_PARAM] ?? null;

        if (is_null($boardID))
            throw new GameValidationException("Invalid board request", ["Missing parameter `" . self::API_BOARD_ID_PARAM ."` from request"]);

        $filteredBoardId = filter_var($boardID, FILTER_VALIDATE_INT);

        if (
            $filteredBoardId === false ||
            $filteredBoardId <= 0
        )
            throw new GameValidationException( "Invalid board request", ["Board ID must be integer greater than 0"]);

            return new self(
                $db,
                $filteredBoardId
            );
    }

    /**
     * @throws ApiException
     */
    public function getTheBoard(): Board
    {
        $boardRow = $this->boardRepo->getBoardByID($this->boardId);

        if (!is_array($boardRow))
            throw new ValidationException( "Failed to get requested board");

        $tokens = $this->boardRepo->getTokensByBoardID($this->boardId);

        return Board::fromDB($boardRow, $tokens);
    }
}