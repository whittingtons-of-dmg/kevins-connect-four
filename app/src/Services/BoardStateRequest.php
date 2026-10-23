<?php

namespace MediumDubb\ConnectFour\Services;

use MediumDubb\ConnectFour\Exceptions\ApiException;

final readonly class BoardStateRequest
{
    private const string BOARD_PARAM = 'boardId';

    public function __construct(
        private int  $board,
    ){}

    /**
     * @throws ApiException
     */
    public static function fromQueryParams(): self {
        $boardId = $_GET[self::BOARD_PARAM] ?? null;

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            throw new ApiException("Invalid request method", 405);
        }

        if (
            $boardId === null || $boardId === ''
        ) {
            throw new ApiException("Incomplete token request", 400);
        }

        if (
            (!filter_var($boardId, FILTER_VALIDATE_INT) || $boardId === '0' )
        ) {
            throw new ApiException("Invalid token request", 400);
        }

        return new self(
            board: $_GET[self::BOARD_PARAM],
        );
    }

    public function getBoardID(): int {
        return $this->board;
    }
}