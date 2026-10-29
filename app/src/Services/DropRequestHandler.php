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
        $boardRow = $this->boardRepo->getBoardByID($this->boardId);

        if (!$boardRow)
            throw new NotFoundException("Invalid token request", ['The requested board doesn\'t exist']);

        return Board::fromDB($boardRow, $this->getBoardTokens());
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
        return Board::dbToTokenList($tokens);
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
        $this->tokenRepo->setToken($token);
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
        if ($winnerId = $this->getWinner($board, $board->popToken())) {
            $this->boardRepo->setBoardWinner($winnerId, $board->getBoardID());
            $board->setWinner($winnerId);
        }
    }

    private function boardFull(Board $board): bool
    {
        return count($board->getTokens()) >= 42;
    }

    /**
     * @throws ApiException
     */
    private function getWinner(Board $board, Token $token): bool|int
    {
        $currentRow = $token->getBoardRow();
        $currentColumn = $token->getBoardColumn();
        $skipL2R = $this->skipCheck([$currentRow, $currentColumn]);
        $skipR2L = $this->skipCheck([$currentRow, $currentColumn], false);
        $playerID = $token->getPlayerID();
        $boardRows = $board->getBoardAsArray();
        $connectCounter = 0;
        $winner = false;

        // vertical - from last drop check if vertical is at least 4 rows from bottom, count concurrent player ID's and see if four match in a row
        if ($currentRow <= 3) {
            for ($i = 0; $i < $currentRow; $i++) {
                $connectCounter = ($boardRows[$currentRow + $i][$currentColumn] === $playerID) ? $connectCounter + 1 : 0;
                if ($winner = ($connectCounter === 4)) { break; }
            }
            $connectCounter = 0;
        }

        // horizontal - on same row as current token, start at beginning and count concurrent player ID's and see if four match in a row
        if (!$winner) {
            for ($i = 0; $i < $board->cols; $i++) {
                $connectCounter = ($boardRows[$currentRow][$i] === $playerID) ? $connectCounter + 1 : 0;
                if ($winner = ($connectCounter === 4)) { break; }
            }
            $connectCounter = 0;
        }

        // left horizontal - find topmost cord and traverse diagonal in downward left direction counting concurrent player ID's and see if four match in a row
        if (!$winner && !$skipL2R) {
            $topLeftDownCord = $this->getTopLeftCord($currentRow, $currentColumn);
            $newCurrRow = $topLeftDownCord[0];
            $newCurrCol = $topLeftDownCord[1];
            for ($i = 0; $i < 5; $i++) {
                $connectCounter = ($boardRows[$newCurrRow][$newCurrCol] === $playerID) ? $connectCounter + 1 : 0;
                $newCurrRow--;
                $newCurrCol++;
                if ($newCurrRow > 5 || $newCurrCol > 6 || ($winner = ($connectCounter === 4))) { break; }
            }
            $connectCounter = 0;
        }

        // right horizontal - find topmost cord and traverse diagonal in downward right direction counting concurrent player ID's and see if four match in a row
        if (!$winner && !$skipR2L) {
            $topRightDownCord = $this->getTopRightCord($currentRow, $currentColumn);
            $newCurrRow = $topRightDownCord[0];
            $newCurrCol = $topRightDownCord[1];
            for ($i = 0; $i < 5; $i++) {
                $connectCounter = ($boardRows[$newCurrRow][$newCurrCol] === $playerID) ? $connectCounter + 1 : 0;
                $newCurrRow--;
                $newCurrCol--;
                if ($newCurrRow > 5 || $newCurrCol < 0 || ($winner = ($connectCounter === 4))) { break; }
            }
        }

//      [   L-R         R-L
//          [0,1,2,3,4,5,6], row 5
//          [0,1,2,3,4,5,6],
//          [0,1,2,3,4,5,6],
//          [0,1,2,3,4,5,6],
//          [0,1,2,3,4,5,6],
//          [0,1,2,3,4,5,6], row 0
//      ]   R-L         L-R

        /** Right-Left | (row + col) > 5 ? [5, (((row+col) % 5) - 6)] : [(row + col),6]
         * if cord [5,3] topLeft = [5, 3]
         * if cord [4,3] topLeft = [5, 4]
         * if cord [3,3] topLeft = [5, 5]
         * if cord [2,3] topLeft = [5, 6]
         * if cord [1,3] topLeft = [4, 6]
         * if cord [0,3] topLeft = [3, 6]
         * ------------------------------
         * Left-Right | (row + col) > 5 ? [5, ((row + col) % 5)] : [(row + col), 0]
         * if cord [5,3] topLeft = [5, 3]
         * if cord [4,3] topLeft = [5, 2]
         * if cord [3,3] topLeft = [5, 1]
         * if cord [2,3] topLeft = [5, 0]
         * if cord [1,3] topLeft = [4, 0]
         * if cord [0,3] topLeft = [3, 0]
         */

        if ( $winner ) {
            return $playerID;
        }

        return false;
    }

    private function skipCheck(array $rowColCords, bool $checkR2L = true): bool
    {
        $direction = $checkR2L ? 'right-left' : 'left-right';
        $deadCords = [
            'left-right' => [
                5 => [0,1,2],
                4 => [0,1],
                3 => [0],
                2 => [6],
                1 => [5,6],
                0 => [4,5,6],
            ],
            'right-left' => [
                // row => [cols]
                5 => [4,5,6],
                4 => [5,6],
                3 => [6],
                2 => [0],
                1 => [0,1],
                0 => [0,1,2]
            ]
        ];

        return in_array($rowColCords[1], $deadCords[$direction][$rowColCords[0]]);
    }

    private function getTopLeftCord($currentRow, $currentColumn): array
    {
        $sum = $currentRow + $currentColumn;
        return $sum > 5 ? [5, (($sum % 5) - 6)] : [$sum ,6];
    }

    private function getTopRightCord($currentRow, $currentColumn): array
    {
        $sum = $currentRow + $currentColumn;
        return $sum > 5 ? [5, ($sum % 5)] : [$sum, 0];
    }
}