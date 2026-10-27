<?php

namespace MediumDubb\ConnectFour\Repositories;

use Exception;
use MediumDubb\ConnectFour\Domains\Token;
use MediumDubb\ConnectFour\Exceptions\ApiException;
use MediumDubb\ConnectFour\Exceptions\GameConflictException;
use MediumDubb\ConnectFour\Exceptions\GameValidationException;
use MediumDubb\ConnectFour\PDOConnector;
use PDO;
use PDOException;

class BoardRepo
{
    private PDOConnector $db;
    private const string TABLE = 'boards';

    public function __construct() {
        $this->db = new PDOConnector();
    }

    private function boardIDExists(string|int $boardID):bool
    {
        $stmt = $this->db->run("SELECT 1 FROM your_table WHERE id = :id LIMIT 1", [
            'id' => $boardID
        ]);

        return (bool) $stmt->fetchColumn();
    }

    private function insertPlayerID(string|int $playerID): false|string
    {
        $this->db->run(
            "INSERT INTO " . self::TABLE . " (player1) VALUES (:player_one_id)",
            [
                'player_one_id' => $playerID,
            ]
        );

        return $this->db->pdo->lastInsertId(); // returns PK from last edited record, use with caution
    }

    private function getBoardRowByID(string|int $boardID): array|false
    {
        $stmt = $this->db->run(
            "SELECT * FROM " . self::TABLE . " WHERE id = :boardID LIMIT 1",
            [
                ':boardID' => $boardID
            ]
        );

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function setPlayerTwo(string|int $boardID, string|int $playerTwoID): int|false
    {
        $stmt = $this->db->run(
            "UPDATE " . self::TABLE . "
                    SET (player2, current_player) = (:player_two_id, :current_player_id)
                    WHERE id = :id
                    AND player2 IS NULL",
            [
                'player_two_id' => $playerTwoID,
                'current_player_id' => $playerTwoID,
                'id' => $boardID,
            ]
        );

        return $stmt->rowCount();
    }

    private function getOpenBoard(): int|string|false
    {
        $stmt = $this->db->run(
            "SELECT id FROM " . self::TABLE . " WHERE player2 IS NULL LIMIT 1"
        );

        return $stmt->fetchColumn();
    }

    private function setWinner(string|int $winnerID, string|int $boardID): void
    {
        $this->db->run(
            "UPDATE " . self::TABLE . "
                    SET winner = :winner_id
                    WHERE id = :id
                    AND winner = NULL",
            [
                'winner_id' => $winnerID,
                'id' => $boardID
            ]
        );
    }

    private function getNextPlayerID(string|int $boardID): int|string|false
    {
        $stmt = $this->db->run(
            "SELECT 
                CASE 
                    WHEN current_player = player1 THEN player2
                    ELSE player1
                END AS opposing_player_id
            FROM " . self::TABLE . "
            WHERE id = :boardID",
            [
                'boardID' => $boardID
            ]
        );

        return $stmt->fetchColumn();
    }

    private function setNextPlayerID(string|int $playerID, string|int $boardID): int|string|false
    {
        $this->db->run(
            "UPDATE " . self::TABLE . "
                    SET current_player_id = :current_player_id
                    WHERE id = :id
                    AND board_finished = 0",
            [
                'current_player_id' => $playerID,
                'id' => $boardID
            ]
        );

        return $playerID;
    }

    private function getBoardPlayerIDs(string|int $boardID): array|null
    {
        $stmt = $this->db->run(
            "SELECT 
                    player1 AS player_one_id,
                    player2 AS player_two_id
                    FROM " . self::TABLE . "
                    WHERE id = :boardID
                    AND board_finished = 0
                    LIMIT 1",
            [
                'boardID' => $boardID,
            ]
        );

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function getBoardTokens(string|int $boardID): array|false
    {
        $stmt = $this->db->run(
            "SELECT 
                    board,
                    player,
                    board_column,
                    board_row
                    FROM " . self::TABLE ."
                    WHERE board = :boardID
                    ORDER BY board_column ASC, board_row ASC",
            [
                'boardID' => $boardID,
            ]
        );

        return  $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @throws ApiException
     */
    public function getBoardByID(int $boardID): array|false
    {
        try {
            return $this->getBoardRowByID($boardID);
        } catch (PDOException $e) {
            // todo For all DB exceptions we need to log exception error, but not return information to API response
            throw new ApiException('Board lookup failed');
        }
    }

    /**
     * @throws ApiException
     */
    public function create(string|int $playerID): array
    {
        $filteredPlayerID = filter_var($playerID, FILTER_VALIDATE_INT);

        if ($filteredPlayerID === false)
            throw new GameValidationException('Player ID is invalid', ['The player ID must be a positive integer']);


        if (filter_var($playerID, FILTER_VALIDATE_INT)) {
            $playerID = intval($playerID);
        } else {
            throw new GameValidationException('Player ID is invalid', ['The ID: "' . $playerID . '" is invalid.']);
        }

        try {
           $boardID = $this->insertPlayerID($playerID);
        } catch (PDOException|Exception $e) {
            // todo For all DB exceptions we need to log exception error, but not return information to API response
            throw new ApiException('Internal server error: unable to create');
        }

        return $this->getBoardByID($boardID);
    }

    /**
     * @throws ApiException
     */
    public function join(string|int $boardID, string|int $playerTwoID): array
    {
        $filteredBoardID = filter_var($boardID, FILTER_VALIDATE_INT);

        if ($filteredBoardID === false)
            throw new GameValidationException('board ID is invalid', ['The board ID must be a positive integer']);

        $filteredPlayerTwoID = filter_var($playerTwoID, FILTER_VALIDATE_INT);

        if ($filteredPlayerTwoID === false)
            throw new GameValidationException('Player ID is invalid', ['The player ID must be a positive integer']);

        try {
            $count = $this->setPlayerTwo($filteredBoardID, $filteredPlayerTwoID);

            if ($count === 0) {
                throw new GameConflictException('You cannot join this board');
            }
        } catch (PDOException $e) {
            // todo For all DB exceptions we need to log exception error, but not return information to API response
            throw new ApiException('Internal server error: unable to join.');
        }

        return $this->getBoardByID($filteredBoardID);
    }

    /**
     * @throws ApiException
     */
    public function getOpenBoardID(): string|int|null
    {
        try {
            $boardID = $this->getOpenBoard();
            if ($boardID)
                return $boardID;
            else
                throw new GameValidationException('No open boards');
        } catch (PDOException $e) {
            // todo For all DB exceptions we need to log exception error, but not return information to API response
            throw new ApiException('Internal server error: failed on open board lookup',);
        }
    }

    /**
     * @throws ApiException
     */
    public function boardExists(string|int $boardID): bool
    {
        $filteredBoardID = filter_var($boardID, FILTER_VALIDATE_INT);

        if ($filteredBoardID === false)
            throw new GameValidationException('board ID is invalid', ['The board ID must be a positive integer']);

        try {
            return $this->boardIDExists($filteredBoardID);
        } catch (PDOException $e) {
            // todo For all DB exceptions we need to log exception error, but not return information to API response
            throw new ApiException('Internal server error: failed on open board lookup',);
        }
    }

    /**
     * @throws ApiException
     */
    public function setBoardWinner(string|int $winnerID, string|int $boardID): void
    {
        $filteredWinnerID = filter_var($winnerID, FILTER_VALIDATE_INT);

        if ($filteredWinnerID === false)
            throw new GameValidationException('winner ID is invalid', ['The winner ID must be a positive integer']);

        $filteredBoardID = filter_var($boardID, FILTER_VALIDATE_INT);

        if ($filteredBoardID === false)
            throw new GameValidationException('board ID is invalid', ['The board ID must be a positive integer']);

        try {
            $this->setWinner($filteredWinnerID, $filteredBoardID);
        } catch (PDOException $e) {
            // todo For all DB exceptions we need to log exception error, but not return information to API response
            throw new ApiException('Internal server error: failed persisting winner');
        }
    }

    /**
     * @throws ApiException
     */
    public function updateTurn(string|int $boardID): string|int
    {
        $filteredBoardID = filter_var($boardID, FILTER_VALIDATE_INT);

        if ($filteredBoardID === false)
            throw new GameValidationException('board ID is invalid', ['The board ID must be a positive integer']);

        try {
            $nextPlayerID = $this->getNextPlayerID($boardID);

            if (!$nextPlayerID)
                throw new GameConflictException('Failed to get next player');
        } catch (PDOException $e) {
            // todo For all DB exceptions we need to log exception error, but not return information to API response
            throw new ApiException('Internal server error: failed on turn assignment');
        }

        return $this->assignCurrentPlayer($boardID, $nextPlayerID);
    }

    /**
     * @throws ApiException
     */
    private function assignCurrentPlayer(string $boardID, string $playerID): string|int
    {
        try {
            return $this->setNextPlayerID($boardID, $playerID);
        } catch (PDOException $e) {
            // todo For all DB exceptions we need to log exception error, but not return information to API response
            throw new ApiException('Internal server error: failed turn update');
        }
    }

    /**
     * @throws ApiException
     */
    public function getRoomPlayerIDs(string $boardID): ?array
    {
        $filteredBoardID = filter_var($boardID, FILTER_VALIDATE_INT);

        if ($filteredBoardID === false)
            throw new GameValidationException('room ID is invalid', ['The board ID must be a positive integer']);

        try {
            return $this->getBoardPlayerIDs($filteredBoardID);
        } catch (PDOException $e) {
            // todo For all DB exceptions we need to log exception error, but not return information to API response
            throw new ApiException('Internal server error: failed get board players');
        }
    }

    /**
     * @throws ApiException
     */
    public function getTokensByBoardID(string|int $boardID): array
    {
        $filteredBoardID = filter_var($boardID, FILTER_VALIDATE_INT);

        if ($filteredBoardID === false)
            throw new GameValidationException('board ID is invalid', ['The board ID must be a positive integer']);

        try {
            $tokens = $this->getBoardTokens($filteredBoardID);
            if (!empty($tokens)) {
                return array_map(fn($row) => Token::fromDB($row), $tokens);
            } else {
                return [];
            }
        } catch (PDOException $e) {
            throw new ApiException('Failed to get tokens');
        }
    }
}