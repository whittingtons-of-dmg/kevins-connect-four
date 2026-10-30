<?php

namespace MediumDubb\ConnectFour\Services;

use MediumDubb\ConnectFour\Domains\Board;
use MediumDubb\ConnectFour\Domains\Token;
use MediumDubb\ConnectFour\Exceptions\ApiException;
use MediumDubb\ConnectFour\Exceptions\GameConflictException;
use MediumDubb\ConnectFour\Exceptions\GameValidationException;
use MediumDubb\ConnectFour\Exceptions\NotFoundException;
use MediumDubb\ConnectFour\PDOConnector;
use MediumDubb\ConnectFour\Repositories\BoardRepo;
use MediumDubb\ConnectFour\Repositories\TokenRepo;

class DropRequestHandler
{
    private const string BOARD_PARAM = 'boardId';
    private const string PLAYER_PARAM = 'playerId';
    private const string COLUMN_PARAM = 'column';

    private TokenRepo $tokenRepo;
    private BoardRepo $boardRepo;

    public function __construct(private readonly PDOConnector $db, private readonly int $boardId, private readonly int $playerId, private readonly int $column)
    {
        $this->tokenRepo = new TokenRepo($this->db);
        $this->boardRepo = new BoardRepo($this->db);
    }

    /**
     * @throws ApiException
     * @throws GameValidationException
     */
    public static function buildRequestHandler(PDOConnector $db): self
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET')
            throw new GameValidationException("Invalid request method", ["This endpoint requires a GET request method"]);

        $playerId = $_GET[self::PLAYER_PARAM] ?? null;
        $boardId = $_GET[self::BOARD_PARAM] ?? null;
        $column = $_GET[self::COLUMN_PARAM] ?? null;

        $filteredPlayerId = filter_var($playerId, FILTER_VALIDATE_INT);
        $filteredBoardId = filter_var($boardId, FILTER_VALIDATE_INT);
        $filteredColumn = filter_var($column, FILTER_VALIDATE_INT);

        if (
            (
                $filteredBoardId === false ||
                $filteredBoardId <= 0
            )
        )
            throw new GameValidationException("Invalid token request", ['`' . self::BOARD_PARAM . '` parameter is required and must be a positive integer greater than zero']);

        if (
            (
                $filteredPlayerId === false ||
                $filteredPlayerId <= 0
            )
        )
            throw new GameValidationException("Invalid token request", ['`' . self::PLAYER_PARAM . '` parameter is required and must be a positive integer greater than zero']);

        if (
            (
                $filteredColumn === false ||
                $filteredColumn < 0 ||
                $filteredColumn >= 7
            )
        )
            throw new GameValidationException("Invalid token request", ['`' . self::COLUMN_PARAM . '` parameter is required with seven possible columns values (int)[0-6].']);

        return new self($db, $boardId, $playerId, $column);
    }

    /**
     * @throws ApiException
     */
    public function updateAndGetBoard(Board $board, Token $token): Board
    {
        $this->setToken($board, $token);
        $this->setWinnerIfExists($board);

        if (!$this->matchEnded($board))
            $this->updateTurn($board);

        return $board;
    }

    /**
     * @throws ApiException
     * @throws NotFoundException
     */
    public function getTheBoard(): Board
    {
        $this->validateBoard();

        $boardRow = $this->boardRepo->getBoardByID($this->boardId);

        if (!$boardRow)
            throw new NotFoundException("Invalid token request", ['The requested board doesn\'t exist']);

        return Board::fromDB($boardRow, $this->getBoardTokens());
    }

    /**
     * @throws ApiException
     * @throws NotFoundException
     */
    private function validateBoard(): void
    {
        $completed = $this->boardRepo->boardCompleted($this->boardId);

        if ($completed)
            throw new GameConflictException("This board has been completed", ['The requested board is no longer playable']);
    }

    /**
     * @throws GameValidationException
     * @throws ApiException
     */
    public function getColumnRow(): int
    {
        $columnRow = $this->tokenRepo->getColRowCount($this->boardId, $this->column);

        if ($columnRow > 6)
            throw new GameValidationException('The selected board column is full', ['This column has more than six tokens']);

        return $columnRow;
    }

    public function getTheToken(int $row): Token
    {
        return new Token($this->boardId, $this->playerId, $this->column, $row);
    }

    /**
     * @throws ApiException
     */
    private function getBoardTokens(): array
    {
        $tokens = $this->boardRepo->getTokensByBoardID($this->boardId);
        return Board::getTokenObjectList($tokens);
    }

    private function matchEnded(Board $board): bool
    {
        return $board->getWinner() || $this->boardFull($board);
    }

    /**
     * @throws ApiException
     */
    private function setToken(Board $board, Token $token): void
    {
        $currentPlayerId = $this->boardRepo->getBoardCurrentPlayerId($board->getBoardID());

        if ( $currentPlayerId === false || ($currentPlayerId !== $token->getPlayerId()) )
            throw new GameValidationException("Invalid token request: It is not your turn");

        $tokenSet = $this->tokenRepo->setToken($token);

        if (!$tokenSet)
            throw new GameConflictException("Token failed to set");

        $board->setToken($token);
    }

    /**
     * @throws ApiException
     */
    private function updateTurn(Board $board): void
    {
        $nextPlayerId = $this->boardRepo->getNextPlayerID($board->getBoardID());

        if (!$nextPlayerId)
            throw new GameConflictException('Failed to get next player');

        $this->boardRepo->assignCurrentPlayer($board->getBoardID(), $nextPlayerId);

        $board->setCurrentPlayer($nextPlayerId);
    }

    /**
     * @throws ApiException
     */
    private function setWinnerIfExists(Board $board): void
    {
        if ($winnerId = $this->getWinner($board)) {
            $winnerSet = $this->boardRepo->setBoardWinner($winnerId, $board->getBoardID());

            if (!$winnerSet)
                throw new GameConflictException('Failed to set winner');

            $board->setWinner($winnerId);
        }
    }

    private function boardFull(Board $board): bool
    {
        return $board->boardIsFull();
    }

    /**
     * @throws ApiException
     */
    private function getWinner(Board $board): bool|int
    {
        $currentPlayer = $board->popToken()->getPlayerID(); // assuming we are grabbing the playerId from the token just set who we have already validated
        $tokenDbList = $this->boardRepo->getPlayerTokens($board->getBoardID(), $currentPlayer);
        $currentPlayerTokens = Board::getTokenObjectList($tokenDbList);
        $tokenPositionList = [];
        
        foreach ($currentPlayerTokens as $token) {
            $positionKey = ($token->getBoardRow() * $board->cols) + ($token->getBoardColumn() + 1);
            $tokenPositionList[$positionKey] = $token->getPlayerId(); // translate tokens to positions starting with 1 all the way to 42
        }

        foreach ($tokenPositionList as $position => $playerId) {
            if (isset($tokenPositionList[$position]) &&
                isset($tokenPositionList[$position + 8]) &&
                isset($tokenPositionList[$position + 16]) &&
                isset($tokenPositionList[$position + 24]))
            {
                return $playerId;
            }

            if (isset($tokenPositionList[$position]) &&
                isset($tokenPositionList[$position - 8]) &&
                isset($tokenPositionList[$position - 16]) &&
                isset($tokenPositionList[$position - 24]))
            {
                return $playerId;
            }

            if (isset($tokenPositionList[$position]) &&
                isset($tokenPositionList[$position + 1]) &&
                isset($tokenPositionList[$position + 2]) &&
                isset($tokenPositionList[$position + 3]))
            {
                return $playerId;
            }

            if (isset($tokenPositionList[$position]) &&
                isset($tokenPositionList[$position - 1]) &&
                isset($tokenPositionList[$position - 2]) &&
                isset($tokenPositionList[$position - 3]))
            {
                return $playerId;
            }

            if (isset($tokenPositionList[$position]) &&
                isset($tokenPositionList[$position + 7]) &&
                isset($tokenPositionList[$position + 14]) &&
                isset($tokenPositionList[$position + 21]))
            {
                return $playerId;
            }

            if (isset($tokenPositionList[$position]) &&
                isset($tokenPositionList[$position - 7]) &&
                isset($tokenPositionList[$position - 14]) &&
                isset($tokenPositionList[$position - 21]))
            {
                return $playerId;
            }
        }

        return false;
    }
}