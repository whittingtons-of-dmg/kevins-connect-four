<?php

namespace MediumDubb\ConnectFour\Repositories;

use MediumDubb\ConnectFour\Domains\Token;
use MediumDubb\ConnectFour\Exceptions\ApiException;
use MediumDubb\ConnectFour\PDOConnector;
use PDO;
use PDOException;

class TokenRepo
{
    private PDOConnector $db;
    private const string TABLE = 'tokens';

    private const array DB_COLUMNS = [
        'id',
        'board_id',
        'player_id',
        'board_column',
    ];

    public function __construct()
    {
        $this->db = new PDOConnector();
    }

    /**
     * @throws ApiException
     */
    public function setToken(int $boardID, int $playerID, int $column, int $row): void
    {
        try {
            $this->db->run(
                "INSERT INTO tokens 
                    (
                        board,
                        player,
                        board_column,
                        board_row
                     ) 
                    VALUES 
                    (
                         :board_id,
                         :player_id,
                         :board_column,
                         :board_row
                     )",
                [
                    'board_id' => $boardID,
                    'player_id' => $playerID,
                    'board_column' => $column,
                    'board_row' => $row
                ]
            );
        } catch (PDOException $e) {
            throw new ApiException('Failed to set token', 500);
        }
    }

    /**
     * @throws ApiException
     */
    public function getTokensByBoardID(int $boardID): array
    {
        try {
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
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($rows)) {
                return array_map(fn($row) => Token::fromDB($row), $rows);
            } else {
                return [];
            }
        } catch (PDOException $e) {
            throw new ApiException('Failed to get tokens', 500);
        }
    }

    /**
     * @throws ApiException
     */
    public function getPlayerMovesByCol(int $boardID): array
    {
        try {
            $stmt = $this->db->run(
                "SELECT id, board_column, JSON_ARRAYAGG(player) 
                        AS all_players 
                        FROM ". self::TABLE ." 
                        WHERE board = :boardID 
                        GROUP BY board_column
                        ORDER BY id ASC;",
                [
                    'boardID' => $boardID,
                ]
            );
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return !empty($rows) ? $rows : [];

        } catch (PDOException $e) {
            throw new ApiException('Failed to get tokens', 500);
        }
    }

    /**
     * @throws ApiException
     */
    public function getColRowCount(int $boardID, int $boardColumn): int
    {
        try {
            $stmt = $this->db->run(
                "SELECT COUNT(*) 
                        AS column_tokens 
                        FROM ". self::TABLE ." 
                        WHERE board = :boardID
                        AND board_column = :boardColumn;",
                [
                    'boardID' => $boardID,
                    'boardColumn' => $boardColumn,
                ]
            );

            return $stmt->fetchColumn();
            // can this return NULL?

        } catch (PDOException $e) {
            throw new ApiException('Failed to get column token count', 500);
        }
    }
}