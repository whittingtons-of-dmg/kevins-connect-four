<?php

namespace MediumDubb\ConnectFour\Services;

use Dotenv\Exception\ValidationException;
use MediumDubb\ConnectFour\Domains\Board;
use MediumDubb\ConnectFour\Exceptions\ApiException;
use MediumDubb\ConnectFour\Exceptions\GameValidationException;
use MediumDubb\ConnectFour\PDOConnector;
use MediumDubb\ConnectFour\Repositories\BoardRepo;

readonly class CreateRequestHandler
{
    private BoardRepo $boardRepo;

    public function __construct(private PDOConnector $db, private int $playerId)
    {
        $this->boardRepo = new BoardRepo($this->db);
    }

    /**
     * @throws GameValidationException
     */
    public static function buildRequestHandler(PDOConnector $db, string|int $playerId): self
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw new GameValidationException("Invalid request method", ["The request method must be POST"]);
        }

        $filteredPlayerId = filter_var($playerId, FILTER_VALIDATE_INT);

        if (
            $filteredPlayerId === false ||
            $filteredPlayerId <= 0
        )
            throw new GameValidationException( "Player assignment error", ["Please report to site admin"]);

        return new self (
            $db,
            $playerId
        );
    }

    /**
     * @throws ApiException
     */
    public function createAndGetBoard(): Board
    {
        $boardId = $this->boardRepo->createAndGetId($this->playerId);
        $boardRow = $this->boardRepo->getBoardById($boardId);

        if (!is_array($boardRow))
            throw new ValidationException('Failed to get requested board');

        return Board::fromDB($boardRow, []);
    }
}