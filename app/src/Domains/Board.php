<?php

namespace MediumDubb\ConnectFour\Domains;

final class Board
{
    public int $cols = 7;
    public int $rows = 6;
    public int $match2Win = 4;

    public function __construct(
        private readonly int     $id,
        private readonly Player  $player1,
        private ?Player          $player2 = null,
        private ?int             $current_player = null,
        private ?int             $winner = null,
        private array            $tokens = [],
    ){}

    public static function fromDB(array $row, array $tokens): self {
        $p1 = Player::fromId($row['player1']);
        $p2 = $row['player2'] ? Player::fromId($row['player2']) : null;

        return new self(
            id: $row['id'],
            player1: $p1,
            player2: $p2,
            current_player: $row['current_player'],
            winner: $row['winner'],
            tokens: $tokens,
        );
    }

    public static function getTokenObjectList(false|array $tokens): array
    {
        if (!empty($tokens)) {
            return array_map(fn($row) => Token::fromDB($row), $tokens);
        } else {
            return [];
        }
    }

    public function getTokens(): array {
        return $this->tokens;
    }

    public function getPlayer1(): Player {
        return $this->player1;
    }

    public function getPlayer2(): ?Player {
        return $this->player2;
    }

    public function getCurrentPlayer(): ?int {
        return $this->current_player;
    }

    public function getWinner(): ?int {
        return $this->winner;
    }

    public function getBoardID(): int
    {
        return $this->id;
    }

    public function popToken(): Token
    {
        return array_pop($this->tokens);
    }

    public function setCurrentPlayer(int $playerId): void
    {
        $this->current_player = $playerId;
    }

    public function setWinner(int $winnerId): void
    {
        $this->winner = $winnerId;
    }

    public function setToken(Token $token): void
    {
        $this->tokens[] = $token;
    }

    public function setPlayer2(Player $p2): void
    {
        $this->player2 = $p2;
    }

    public function boardIsFull(): bool
    {
        return count($this->getTokens()) >= 42;
    }

    public function validateCurrentPlayer(int $playerIdFromRequest, int $sessionPlayerId): bool
    {
        $boardCurrentPlayer = $this->getCurrentPlayer();
        return ( $boardCurrentPlayer === $playerIdFromRequest ) && ($playerIdFromRequest === $sessionPlayerId);
    }
}