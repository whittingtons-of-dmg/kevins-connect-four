<?php

namespace MediumDubb\ConnectFour\Repositories;

use MediumDubb\ConnectFour\Domains\Token;
use MediumDubb\ConnectFour\Exceptions\ApiException;
use MediumDubb\ConnectFour\Exceptions\GameValidationException;
use MediumDubb\ConnectFour\PDOConnector;
use PDOException;

class TokenRepo
{
    private const string TABLE = 'tokens';

    public function __construct(private readonly PDOConnector $db){}

    /**
     * @throws ApiException
     */
    public function setToken(Token $token): bool
    {
        try {
            $stmt = $this->db->run(
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
                    'board_id' => $token->getBoardID(),
                    'player_id' => $token->getPlayerID(),
                    'board_column' => $token->getBoardColumn(),
                    'board_row' => $token->getBoardRow()
                ]
            );

            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            throw new ApiException('Failed to set token',);
        }
    }

    /**
     * @throws ApiException
     * @throws GameValidationException
     */
    public function getColRowCount(int $boardId, int $column): int
    {
        try {
            $stmt = $this->db->run(
                "SELECT COUNT(*) 
                        AS column_tokens 
                        FROM ". self::TABLE ." 
                        WHERE board = :boardID
                        AND board_column = :boardColumn;",
                [
                    'boardID' => $boardId,
                    'boardColumn' => $column,
                ]
            );

            return $stmt->fetchColumn();

        } catch (PDOException $e) {
            throw new ApiException('Failed to get column token count');
        }
    }
}