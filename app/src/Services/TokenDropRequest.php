<?php

namespace MediumDubb\ConnectFour\Services;

use MediumDubb\ConnectFour\Domains\Token;
use MediumDubb\ConnectFour\Exceptions\ApiException;

final readonly class TokenDropRequest
{
    private const string BOARD_PARAM = 'boardId';
    private const string PLAYER_PARAM = 'playerId';
    private const string COLUMN_PARAM = 'column';

    private Token $currentToken;

    public function __construct(
        private int $board,
        private int $player,
        private int $column
    ){
        $this->currentToken = new Token($this->board, $this->player, $this->column);
    }

    /**
     * @throws ApiException
     */
    public static function fromQueryParams(): self {
        $playerId = $_GET[self::PLAYER_PARAM] ?? null;
        $boardId = $_GET[self::BOARD_PARAM] ?? null;
        $column = $_GET[self::COLUMN_PARAM] ?? null;

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            throw new ApiException("Invalid request method", 405);
        }

        if (
            $playerId === null || $boardId === null || $column === null
        ) {
            throw new ApiException("Incomplete token request", 400);
        }

        if (
            (!filter_var($playerId, FILTER_VALIDATE_INT) || $playerId === '0') ||
            (!filter_var($boardId, FILTER_VALIDATE_INT) || $boardId === '0' ) ||
            (filter_var($column, FILTER_VALIDATE_INT) < 0 || filter_var($column, FILTER_VALIDATE_INT) > 6)
        ) {
            throw new ApiException("Invalid token request", 400);
        }

        if (intval($column) > 6 || intval($column) < 0) {
            throw new ApiException("Invalid column", 400);
        }

        return new self(
            board: $boardId,
            player: $playerId,
            column: $column
        );
    }

    public function getCurrentToken(): Token
    {
        return $this->currentToken;
    }

    public function getBoardID(): int
    {
        return $this->board;
    }

    public function getPlayerID(): int
    {
        return $this->player;
    }
}