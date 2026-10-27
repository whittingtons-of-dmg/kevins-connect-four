<?php

namespace MediumDubb\ConnectFour\Services;

use Dotenv\Exception\ValidationException;
use MediumDubb\ConnectFour\Domains\Board;
use MediumDubb\ConnectFour\Domains\Token;
use MediumDubb\ConnectFour\Exceptions\ApiException;
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

    private PDOConnector $db;

    public function __construct(private readonly Token $token, private readonly Board $board)
    {
        $this->db = new PDOConnector();
    }

    /**
     * @throws ApiException
     * @throws GameValidationException
     */
    public static function fromTokenDropRequest(int $sessionPlayerId): self
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET')
            throw new GameValidationException("Invalid request method", ["This endpoint requires a GET request method"]);

        $playerId = $_GET[self::PLAYER_PARAM] ?? null;
        $boardId = $_GET[self::BOARD_PARAM] ?? null;
        $column = $_GET[self::COLUMN_PARAM] ?? null;

        $filteredPlayerID = filter_var($playerId, FILTER_VALIDATE_INT);
        $filteredBoardID = filter_var($boardId, FILTER_VALIDATE_INT);
        $filteredColumn = filter_var($column, FILTER_VALIDATE_INT);

        if (
            (
                $filteredBoardID === false ||
                $filteredBoardID <= 0
            )
        )
            throw new GameValidationException("Invalid token request", ['`' . self::BOARD_PARAM . '` parameter is required and must be a positive integer greater than zero']);

        if (
            (
                $filteredPlayerID === false ||
                $filteredPlayerID <= 0
            )
        )
            throw new GameValidationException("Invalid token request", ['`' . self::PLAYER_PARAM . '` parameter is required and must be a positive integer greater than zero']);

        if (
            (
                $filteredColumn !== false &&
                $filteredColumn >= 0 &&
                $filteredColumn < 7
            )
        )
            throw new GameValidationException("Invalid token request", ['`' . self::COLUMN_PARAM . '` parameter is required with seven possible columns values (int)[0-6].']);

        $boardRow = new BoardRepo()->getBoardByID($filteredBoardID);

        if (!$boardRow)
            throw new NotFoundException("Invalid token request", ['The requested board doesn\'t exist']);

        $board = Board::fromDB($boardRow);

        if ($board->validateCurrentPlayer($filteredPlayerID, $sessionPlayerId))
            throw new ValidationException("Invalid token request", ['You are not permitted to take a turn at this time']);

        $columnRow = new TokenRepo(new PDOConnector())->getColRowCount($boardId, $column);

        if ($columnRow > 6)
            throw new GameValidationException('The selected board column is full', ['This column has more than six tokens']);

        $token = new Token($boardId, $playerId, $column, $columnRow);

        return new self($token, $board);
    }

    public function getTheBoard(): Board
    {
        return $this->board;
    }

    public function getTheToken(): Token
    {
        return $this->token;
    }

    public function matchEnded(): bool
    {
        return $this->board->getWinner() || $this->boardFull();
    }

    /**
     * @throws ApiException
     */
    public function setToken(): void
    {
        new TokenRepo($this->db)->setToken($this->token);
        $this->board->setToken($this->token);
    }

    /**
     * @throws ApiException
     */
    public function updateTurn(): void
    {
        $nextPlayer = new BoardRepo()->updateTurn($this->board->getBoardID());
        if ($nextPlayer) {
            $this->board->setCurrentPlayer($nextPlayer);
        }
    }

    /**
     * @throws ApiException
     */
    public function setWinnerIfExists(): void
    {
        if ($winnerId = $this->getWinner()) {
            new BoardRepo()->setBoardWinner($winnerId, $this->board->getBoardID());
            $this->board->setWinner($winnerId);
        }
    }

    private function boardFull(): bool
    {
        return count($this->board->getTokens()) >= 42;
    }

    /**
     * @throws ApiException
     */
    private function getWinner(): bool|int
    {
        $currentRow = $this->token->getBoardRow();
        $currentColumn = $this->token->getBoardColumn();
        $skipL2R = $this->skipCheck([$currentRow, $currentColumn]);
        $skipR2L = $this->skipCheck([$currentRow, $currentColumn], false);
        $playerID = $this->token->getPlayerID();
        $boardRows = $this->board->getBoardAsArray();
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
            for ($i = 0; $i < $this->board->cols; $i++) {
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