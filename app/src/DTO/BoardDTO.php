<?php

namespace MediumDubb\ConnectFour\DTO;

use MediumDubb\ConnectFour\Domains\Board;
use MediumDubb\ConnectFour\Domains\Token;
use MediumDubb\ConnectFour\Exceptions\ApiException;

class BoardDTO
{
    public function __construct(
        public int        $id,
        public PlayerDTO  $player1,
        public ?PlayerDTO $player2,
        public ?int       $current_player,
        public ?int       $winner,
        public array      $tokens,
    ) {}

    /**
     * @throws ApiException
     */
    public static function fromDomain(Board $board): self
    {
        $p1 = PlayerDTO::fromDomain($board->getPlayer1());
        $p2 = $board->getPlayer2() ? PlayerDTO::fromDomain($board->getPlayer2()) : null;

        $tokens = empty($board->getTokens()) ? [] :
            array_map(
                fn (Token $token) => TokenDTO::fromDomain($token),
                $board->getTokens()
            );

        return new self(
            id: $board->getBoardID(),
            player1: $p1,
            player2: $p2,
            current_player: $board->getCurrentPlayer(),
            winner: $board->getWinner(),
            tokens: $tokens,
        );
    }

    public function toArray(): array
    {
        $p1 = $this->player1->toArray();
        $p2 = $this->player2?->toArray();
        $tokens = empty($this->tokens) ? [] : array_map(
            fn (TokenDTO $token) => $token->toArray(),
            $this->tokens
        );

        return [
            'id' => $this->id,
            'player1'  => $p1,
            'player2' => $p2,
            'current_player' => $this->current_player,
            'winner' => $this->winner,
            'tokens' => $tokens
        ];
    }
}