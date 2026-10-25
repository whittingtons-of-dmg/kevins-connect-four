<?php

namespace MediumDubb\ConnectFour\Repositories;

use MediumDubb\ConnectFour\Domains\Token;
use MediumDubb\ConnectFour\Exceptions\ApiException;
use MediumDubb\ConnectFour\Exceptions\GameValidationException;
use MediumDubb\ConnectFour\PDOConnector;
use PDOException;

class TokenRepo
{
    private PDOConnector $db;
    private const string TABLE = 'tokens';

    /**
     * @throws GameValidationException
     * @throws ApiException
     */
    public function __construct(private readonly Token $token)
    {
        $this->db = new PDOConnector();
    }

    /**
     * @throws ApiException
     */
    private function enterToken(): void
    {
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
                'board_id' => $this->token->getBoardID(),
                'player_id' => $this->token->getPlayerID(),
                'board_column' => $this->token->getBoardColumn(),
                'board_row' => $this->token->getBoardRow()
            ]
        );
    }

    private function countColumnRows(): int|false
    {
        $stmt = $this->db->run(
            "SELECT COUNT(*) 
                        AS column_tokens 
                        FROM ". self::TABLE ." 
                        WHERE board = :boardID
                        AND board_column = :boardColumn;",
            [
                'boardID' => $this->token->getBoardID(),
                'boardColumn' => $this->token->getBoardColumn(),
            ]
        );

        return $stmt->fetchColumn();
    }

    /**
     * @throws ApiException
     */
    public function setToken(): void
    {
        try {
            $this->enterToken();
        } catch (PDOException $e) {
            throw new ApiException('Failed to set token',);
        }
    }

    /**
     * @throws ApiException
     */
    public function getColRowCount(): int
    {
        try {
            if ( ($count = $this->countColumnRows()) || $count === 0)
                return $count;
            else if ($count > 6)
                throw new GameValidationException('This column is full');
            else
                throw new GameValidationException('Couln count failed');
        } catch (PDOException $e) {
            throw new ApiException('Failed to get column token count');
        }
    }
}