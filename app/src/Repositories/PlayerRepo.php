<?php

namespace MediumDubb\ConnectFour\Repositories;

use MediumDubb\ConnectFour\Exceptions\ApiException;
use MediumDubb\ConnectFour\PDOConnector;
use PDO;
use PDOException;

class PlayerRepo
{
    private const string TABLE_NAME = 'players';

    public function __construct(private readonly PDOConnector $db) {}

    /**
     * @throws ApiException
     */
    public function insertAndGetID(): string|false
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
    public function getPlayerByID(string|int $playerId): array|false
    {

        try {
            $stmt = $this->db->run(
                "SELECT * FROM ". self::TABLE_NAME ." WHERE id = :id",
                [
                    ':id' => $playerId
                ]
            );

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new ApiException('Failed to get player', 500);
        }
    }
}