<?php

namespace MediumDubb\ConnectFour\Repositories;

use MediumDubb\ConnectFour\Exceptions\ApiException;
use MediumDubb\ConnectFour\Exceptions\GameValidationException;
use MediumDubb\ConnectFour\Exceptions\NotFoundException;
use MediumDubb\ConnectFour\PDOConnector;
use PDO;
use PDOException;

class PlayerRepo
{
    private const string TABLE_NAME = 'players';

    public function __construct(private readonly PDOConnector $db) {}

    private function makeNewPlayer(): false|string
    {
        $this->db->run(
            "INSERT INTO ". self::TABLE_NAME ." () VALUES ()"
        );

        return $this->db->pdo->lastInsertId();
    }

    private function getPlayerRow(string|int $playerID): false|array
    {
        $stmt = $this->db->run(
            "SELECT * FROM ". self::TABLE_NAME ." WHERE id = :id",
            [
                ':id' => $playerID
            ]
        );

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * @throws ApiException
     */
    public function getNewPlayerID(): int
    {
        try {
            return $this->makeNewPlayer();
        } catch (PDOException $e) {
            throw new ApiException( 'Failed to create new player', 500);
        }
    }

    /**
     * @throws ApiException
     */
    public function getPlayerByID(string|int $playerID): array
    {
        $filteredPlayerID = filter_var($playerID, FILTER_VALIDATE_INT);

        if ($filteredPlayerID === false)
            throw new GameValidationException('Player ID is invalid', ['The player ID must be a positive integer']);

        try {
            if ($playerRow = $this->getPlayerRow($filteredPlayerID))
                return $playerRow;
            else
                throw new NotFoundException( 'Player not found, or doesn\'t exist');

        } catch (PDOException $e) {
            throw new ApiException('Failed to get player', 500);
        }
    }
}