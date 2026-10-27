<?php

namespace MediumDubb\ConnectFour\DTO;

use MediumDubb\ConnectFour\Domains\Token;
use MediumDubb\ConnectFour\Exceptions\ApiException;

class TokenDTO
{
    public function __construct(
        public int $board,
        public int $player,
        public int $board_column,
        public int $board_row
    ) {}

    /**
     * @throws ApiException
     */
    public static function fromDomain(Token $token): self
    {
        return new self(
            board: $token->getBoardID(),
            player: $token->getPlayerID(),
            board_column: $token->getBoardColumn(),
            board_row: $token->getBoardRow(),
        );
    }

    public function toArray(): array
    {
        return [
            'board'  => $this->board,
            'player' => $this->player,
            'board_column' => $this->board_column,
            'board_row' => $this->board_row,
        ];
    }
}