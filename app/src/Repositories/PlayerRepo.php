<?php

namespace MediumDubb\ConnectFour\Repositories;

use MediumDubb\ConnectFour\Exceptions\ApiException;
use MediumDubb\ConnectFour\PDOConnector;
use PDO;
use PDOException;

class PlayerRepo
{
    private PDOConnector $db;
    private const string TABLE_NAME = 'players';

    private const array DB_COLUMNS = [
        'id',
        'player_name',
    ];

    public function __construct() {
        $this->db = new PDOConnector();
    }

    /**
     * @throws ApiException
     */
    public function getNewPlayerID(): int
    {
        try {
            $this->db->run(
                "INSERT INTO ". self::TABLE_NAME ." () VALUES ()"
            );

            return $this->db->pdo->lastInsertId();
        } catch (PDOException $e) {
            throw new ApiException( 'Failed to create new player', 500);
        }
    }

    /**
     * @throws ApiException
     */
    public function getPlayerByID(string $playerID): array
    {
        try {
            $stmt = $this->db->run(
                "SELECT * FROM ". self::TABLE_NAME ." WHERE id = :id",
                [
                    ':id' => $playerID
                ]
            );

            $playerRow = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($playerRow) {
                return $playerRow;
            }
            else {
                throw new ApiException( 'Player not found', 400);
            }
        } catch (PDOException $e) {
            throw new ApiException('Failed to get player', 500);
        }

    }
}