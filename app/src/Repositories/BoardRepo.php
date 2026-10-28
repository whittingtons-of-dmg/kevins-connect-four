<?php

namespace MediumDubb\ConnectFour\Repositories;

use Exception;
use MediumDubb\ConnectFour\Domains\Token;
use MediumDubb\ConnectFour\Exceptions\ApiException;
use MediumDubb\ConnectFour\Exceptions\GameValidationException;
use MediumDubb\ConnectFour\PDOConnector;
use PDO;
use PDOException;

class BoardRepo
{
    private const string TABLE = 'boards';

    public function __construct(private readonly PDOConnector $db) {}

    private function getBoardTokens(string|int $boardId): array|false
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
                'boardID' => $boardId,
            ]
        );

        return  $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @throws ApiException
     */
    public function getBoardById(int $boardId): array|false
    {
        try {
            $stmt = $this->db->run(
                "SELECT * FROM " . self::TABLE . " WHERE id = :boardID LIMIT 1",
                [
                    ':boardID' => $boardId
                ]
            );

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // todo For all DB exceptions we need to log exception error, but not return information to API response
            throw new ApiException('Board lookup failed');
        }
    }

    /**
     * @throws ApiException
     */
    public function createAndGetId(string|int $playerId): int|false
    {
        try {
            $this->db->run(
                "INSERT INTO " . self::TABLE . " (player1) VALUES (:player_one_id)",
                [
                    'player_one_id' => $playerId,
                ]
            );

            return $this->db->pdo->lastInsertId(); // returns PK from last edited record, use with caution
        } catch (PDOException|Exception $e) {
            // todo For all DB exceptions we need to log exception error, but not return information to API response
            throw new ApiException('Internal server error: unable to create');
        }
    }

    /**
     * @throws ApiException
     */
    public function joinAndGetRowCount(int $boardId, int $playerTwoId): int
    {
        try {
            $stmt = $this->db->run(
                "UPDATE " . self::TABLE . "
                    SET (player2, current_player) = (:player_two_id, :current_player_id)
                    WHERE id = :id
                    AND player2 IS NULL",
                [
                    'player_two_id' => $playerTwoId,
                    'current_player_id' => $playerTwoId,
                    'id' => $boardId,
                ]
            );

            return $stmt->rowCount();

        } catch (PDOException $e) {
            // todo For all DB exceptions we need to log exception error, but not return information to API response
            throw new ApiException('Internal server error: unable to join.');
        }
    }

    /**
     * @throws ApiException
     */
    public function boardExists(string|int $boardId): bool
    {
        try {
            $stmt = $this->db->run("SELECT 1 FROM " . self::TABLE . " WHERE id = :id LIMIT 1", [
                'id' => $boardId
            ]);

            return (bool) $stmt->fetchColumn();
        } catch (PDOException $e) {
            // todo For all DB exceptions we need to log exception error, but not return information to API response
            throw new ApiException('Internal server error: failed on open board lookup',);
        }
    }

    /**
     * @throws ApiException
     */
    public function setBoardWinner(string|int $winnerId, string|int $boardId): void
    {
        try {
            $this->db->run(
                "UPDATE " . self::TABLE . "
                    SET winner = :winner_id
                    WHERE id = :id
                    AND winner = NULL",
                [
                    'winner_id' => $winnerId,
                    'id' => $boardId
                ]
            );
        } catch (PDOException $e) {
            // todo For all DB exceptions we need to log exception error, but not return information to API response
            throw new ApiException('Internal server error: failed persisting winner');
        }
    }

    /**
     * @throws ApiException
     */
    public function getNextPlayerID(string|int $boardId):int|false
    {
        try {
            $stmt = $this->db->run(
                "SELECT 
                CASE 
                    WHEN current_player = player1 THEN player2
                    ELSE player1
                END AS opposing_player_id
                FROM " . self::TABLE . "
                WHERE id = :boardID",
                [
                    'boardID' => $boardId
                ]
            );

            return $stmt->fetchColumn();

        } catch (PDOException $e) {
            // todo For all DB exceptions we need to log exception error, but not return information to API response
            throw new ApiException('Internal server error: failed on turn assignment');
        }
    }

    /**
     * @throws ApiException
     */
    public function assignCurrentPlayer(string $boardID, string $playerID): void
    {
        try {
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
        } catch (PDOException $e) {
            // todo For all DB exceptions we need to log exception error, but not return information to API response
            throw new ApiException('Internal server error: failed turn update');
        }
    }

    /**
     * @throws ApiException
     */
    public function getTokensByBoardID(string|int $boardID): array
    {
        $filteredBoardId = filter_var($boardID, FILTER_VALIDATE_INT);

        if ($filteredBoardId === false)
            throw new GameValidationException('board ID is invalid', ['The board ID must be a positive integer']);

        try {
            $tokens = $this->getBoardTokens($filteredBoardId);
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