<?php

namespace MediumDubb\ConnectFour\Repositories;

use Exception;
use MediumDubb\ConnectFour\Exceptions\ApiException;
use MediumDubb\ConnectFour\PDOConnector;
use PDO;
use PDOException;

class BoardRepo
{
    private PDOConnector $db;
    private const string TABLE = 'boards';

    private const array DB_COLUMNS = [
        'id',
        'player_one_id',
        'player_two_id',
        'current_player_id',
        'winner_id',
        'finished',
    ];

    public function __construct() {
        $this->db = new PDOConnector();
    }

    /**
     * @throws ApiException
     */
    public function getBoardByID(string $boardID): array
    {
        try {
            $stmt = $this->db->run(
                "SELECT * FROM " . self::TABLE . " WHERE id = :boardID LIMIT 1",
                [
                    ':boardID' => $boardID
                ]
            );
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new ApiException('Internal server error: lookup failed', 500);
        }
    }

    /**
     * @throws ApiException
     */
    public function create(string $player_id): array
    {
        if (filter_var($player_id, FILTER_VALIDATE_INT)) {
            $player_id = intval($player_id);
        } else {
            throw new ApiException('Invalid player ID', 403);
        }

        try {
            $this->db->run(
                "INSERT INTO " . self::TABLE . " (player1) VALUES (:player_one_id)",
                [
                    'player_one_id' => $player_id,
                ]
            );

            $boardID = $this->db->pdo->lastInsertId();
        } catch (PDOException|Exception $e) {
            throw new ApiException('Internal server error: unable to create');
        }

        return $this->getBoardByID($boardID);
    }

    /**
     * @throws ApiException
     */
    public function join(string $board_id, string $player_two_id): array
    {
        try {
            $stmt = $this->db->run(
                "UPDATE " . self::TABLE . "
                    SET (player2, current_player) = (:player_two_id, :current_player_id)
                    WHERE id = :id
                    AND player2 IS NULL",
                [
                    'player_two_id' => $player_two_id,
                    'current_player_id' => $player_two_id
                ]
            );

            if ($stmt->rowCount() === 0) {
                throw new ApiException('You cannot join this board', 403);
            }

        } catch (PDOException $e) {
            throw new ApiException('Internal server error: unable to join.', 403);
        }

        return $this->getBoardByID($board_id);
    }

    /**
     * @throws ApiException
     */
    public function getOpenBoardID(): ?string
    {
        try {
            $stmt = $this->db->run(
                "SELECT id FROM " . self::TABLE . " WHERE player2 IS NULL LIMIT 1"
            );

            $id = $stmt->fetchColumn();
        } catch (PDOException $e) {
            throw new ApiException('Internal server error: failed on open board lookup', 500);
        }

        return $id;
    }

    /**
     * @throws ApiException
     */
    public function setBoardWinner(string $winnerID, string $boardID): string|int
    {
        try {
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
        } catch (PDOException $e) {
            throw new ApiException('Internal server error: failed persisting winner', 500);
        }

        return $winnerID;
    }

    /**
     * @throws ApiException
     */
    public function updateTurn(string $boardID): string|int
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
                    'boardID' => $boardID
                ]
            );

            $nextPlayerID = $stmt->fetchColumn();
        } catch (PDOException $e) {
            throw new ApiException('Internal server error: failed on turn assignment', 500);
        }

        if ($nextPlayerID) {
            return $this->assignCurrentPlayer($boardID, $nextPlayerID);
        } else {
            throw new ApiException('Failed to get next player');
        }
    }

    /**
     * @throws ApiException
     */
    private function assignCurrentPlayer(string $boardID, string $playerID): string|int
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
            throw new ApiException('Internal server error: failed turn update', 500);
        }

        return $playerID;
    }

    public function getRoomPlayerIDs(string $roomID): ?array
    {
        $stmt = $this->db->run(
            "SELECT 
                    player1 AS player_one_id,
                    player2 AS player_two_id
                    FROM " . self::TABLE . "
                    WHERE id = :roomID
                    AND board_finished = 0
                    LIMIT 1",
            [
                'roomID' => $roomID,
            ]
        );

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}