<?php

namespace MediumDubb\ConnectFour\Services;

use MediumDubb\ConnectFour\Domains\Board;
use MediumDubb\ConnectFour\Domains\Token;
use MediumDubb\ConnectFour\DTO\BoardResponse;
use MediumDubb\ConnectFour\Exceptions\ApiException;

final readonly class TokenHandler
{
    private Token $currentToken;
    private Board $board;
    private TokenDropRequest $request;

    /**
     * @throws ApiException
     */
    public function __construct(private string|int $sessionPlayerID)
    {
        $this->request = TokenDropRequest::fromQueryParams();
        $this->currentToken = $this->request->getCurrentToken();
        $this->board = Board::getByID($this->request->getBoardID());
    }

    /**
     * @throws ApiException
     */
    public function getResponseObj(): array
    {
        $this->validatePlayerMove();
        $this->board->setToken($this->request);
        $winnerID = $this->getWinner();

        if ($winnerID) {
            $this->board->setWinner($winnerID);
        }

        return BoardResponse::fromDomain($this->board)->toArray();
    }

    /**
     * @throws ApiException
     */
    private function validatePlayerMove(): void
    {
        $sessionPlayerID = $this->sessionPlayerID;
        $requestPlayerID = $this->currentToken->getPlayerID();
        $currentPlayerID = $this->board->getCurrentPlayer();

        if ($sessionPlayerID !== $requestPlayerID)
            throw new ApiException( 'Do not make moves for other players', 400);

        if ($currentPlayerID !== $requestPlayerID)
            throw new ApiException('It\'s not your turn', 400);

        if ($this->currentToken->getBoardRow() >= 5) // 0-5 | 6 total
            throw new ApiException('Invalid token placement, column is full', 400);
    }

    /**
     * @throws ApiException
     */
    private function getWinner(): bool|int
    {
        $currentRow = $this->currentToken->getBoardRow();
        $currentColumn = $this->currentToken->getBoardColumn();
        $skipL2R = $this->skipCheck([$currentRow, $currentColumn]);
        $skipR2L = $this->skipCheck([$currentRow, $currentColumn], false);
        $playerID = $this->currentToken->getPlayerID();
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