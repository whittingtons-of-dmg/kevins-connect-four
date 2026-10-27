<?php

namespace MediumDubb\ConnectFour\Domains;

use MediumDubb\ConnectFour\Exceptions\ApiException;
use MediumDubb\ConnectFour\Repositories\BoardRepo;

final class Board
{
    public int $cols = 7;
    public int $rows = 6;

    public function __construct(
        private readonly int     $id,
        private readonly Player  $player1,
        private ?Player          $player2 = null,
        private ?int             $current_player = null,
        private ?int             $winner = null,
        private array            $tokens = [],
    ){}

    /**
     * @throws ApiException
     */
    public static function fromDB(array $row): self {
        $tokens = new BoardRepo()->getTokensByBoardID($row['id']);

        return new self(
            id: $row['id'],
            player1: $row['player1'],
            player2: $row['player2'],
            current_player: $row['current_player'],
            winner: $row['winner'],
            tokens: $tokens,
        );
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

    public function validateCurrentPlayer(int $playerIdFromRequest, int $sessionPlayerId): bool
    {
        $boardCurrentPlayer = $this->getCurrentPlayer();
        return ( $boardCurrentPlayer === $playerIdFromRequest ) && ($playerIdFromRequest === $sessionPlayerId);
    }

    /**
     * @throws ApiException
     */
    public function getBoardAsArray(): array
    {
        $boardRows = [];
        $tokens = $this->getTokens();

        for ($r = ($this->rows - 1); $r >= 0; $r--) {
            for ($c = 0; $c < $this->cols; $c++) {
                $pID = null;
                foreach ($tokens as $tokenIndex => $token) {
                    if ($token->getBoardColumn() === $c && $token->getBoardRow() === $r) {
                        $pID = $token->getPlayerID();
                        unset($tokens[$tokenIndex]);
                        break;
                    }
                }
                $boardRows[$r][] = $pID;
            }
        }

        return $boardRows;
    }
}