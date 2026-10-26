<?php

namespace MediumDubb\ConnectFour\Domains;

use MediumDubb\ConnectFour\Exceptions\ApiException;
use MediumDubb\ConnectFour\Repositories\BoardRepo;
use MediumDubb\ConnectFour\Repositories\TokenRepo;
use MediumDubb\ConnectFour\Services\TokenDropRequest;

final class Board
{
    public function __construct(
        private readonly int     $id,
        private readonly Player  $player1,
        private readonly ?Player $player2 = null,
        private ?int             $current_player = null,
        private ?int             $winner = null,
        public readonly int      $cols = 7,
        public readonly int      $rows = 6,
    ){}

    public static function fromDB(array $row): self {
        return new self(
            id: $row['id'],
            player1: $row['player1'],
            player2: $row['player2'],
            current_player: $row['current_player'],
            winner: $row['winner'],
        );
    }

    /**
     * @throws ApiException
     */
    public function getTokens(): array {
        return new BoardRepo()->getTokensByBoardID($this->getBoardID());
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

    /**
     * @throws ApiException
     */
    public function updateTurn(int $boardID): void
    {
        // todo - move logic into the respected request handler service classes
        $nextPlayer = new BoardRepo()->updateTurn($boardID);
        if ($nextPlayer) {
            $this->current_player = $nextPlayer;
        }
    }

    /**
     * @throws ApiException
     */
    public function setWinner(int $playerID): void
    {
        // todo - move logic into the respected request handler service classes
        $winnerID = new BoardRepo()->setBoardWinner($playerID, $this->id);
        if ($winnerID) {
            $this->winner = $winnerID;
        }
    }

    /**
     * @throws ApiException
     */
    public function setToken(TokenDropRequest $tokenDropRequest): void
    {
        // todo - move logic into the respected request handler service classes
        new TokenRepo($tokenDropRequest->getCurrentToken())->setToken();
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