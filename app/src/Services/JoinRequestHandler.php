<?php

namespace MediumDubb\ConnectFour\Services;

use Dotenv\Exception\ValidationException;
use MediumDubb\ConnectFour\Domains\Board;
use MediumDubb\ConnectFour\Exceptions\ApiException;
use MediumDubb\ConnectFour\Exceptions\GameValidationException;
use MediumDubb\ConnectFour\Exceptions\NotFoundException;
use MediumDubb\ConnectFour\PDOConnector;
use MediumDubb\ConnectFour\Repositories\BoardRepo;

class JoinRequestHandler
{
    private const string API_BOARD_ID_PARAM = 'boardId';

    private BoardRepo $boardRepo;

    public function __construct(private readonly PDOConnector $db, private readonly int $boardId, private readonly int $playerId)
    {
        $this->boardRepo = new BoardRepo($this->db);
    }

    /**
     * @throws GameValidationException
     */
    public static function buildRequestHandler(PDOConnector $db, string|int $playerId): self
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET')
            throw new GameValidationException( "Invalid request method", ["The request method must be GET"] );

        $boardID = $_GET[self::API_BOARD_ID_PARAM] ?? null;
        $filteredPlayerId = filter_var($playerId, FILTER_VALIDATE_INT);

        if (is_null($boardID))
            throw new GameValidationException("Invalid board request", ["Missing parameter `" . self::API_BOARD_ID_PARAM . "` from request"]);

        if (
            $filteredPlayerId === false ||
            $filteredPlayerId <= 0
        )
            throw new GameValidationException( "Player assignment error", ["Please report to site admin"]);

        $filteredBoardId = filter_var($boardID, FILTER_VALIDATE_INT);

        if (
            $filteredBoardId === false ||
            $filteredBoardId <= 0
        )
            throw new GameValidationException( "Invalid board request", ["Parameter `" . self::API_BOARD_ID_PARAM . "` must be integer greater than 0"]);

        return new self (
            $db,
            $filteredBoardId,
            $playerId
        );
    }

    /**
     * @throws ApiException
     */
    public function joinAndGetBoard(): Board
    {
        $exists = $this->boardRepo->boardExists($this->boardId);

        if (!$exists)
            throw new NotFoundException("The board you are trying to join doesn't exist");

        if ($this->boardRepo->boardIsFull($this->boardId))
            throw new GameValidationException('The requested board is full');

        if ($this->boardRepo->getBoardPlayer1Id($this->boardId) === $this->playerId)
            throw new ValidationException("Player 1 and Player 2 must not share the same ID");

        $affectedRowCount = $this->boardRepo->joinAndGetRowCount($this->boardId, $this->playerId);

        if ($affectedRowCount === 0)
            throw new GameValidationException('Board is not joinable');

        $boardRow = $this->boardRepo->getBoardById($this->boardId);

        if ($boardRow === false)
            throw new GameValidationException("Failed to get the board");

        $tokens = $this->boardRepo->getTokensByBoardID($this->boardId);

        $tokens = Board::getTokenObjectList($tokens);

        return Board::fromDB($boardRow, $tokens);
    }
}