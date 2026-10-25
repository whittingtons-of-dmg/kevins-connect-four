<?php

namespace MediumDubb\ConnectFour\Domains;

use MediumDubb\ConnectFour\Exceptions\ApiException;
use MediumDubb\ConnectFour\Repositories\BoardRepo;
use MediumDubb\ConnectFour\Repositories\TokenRepo;
use MediumDubb\ConnectFour\Services\TokenDropRequest;

final class Board
{
    public function __construct(
        private readonly int    $id,
        private int|Player      $player1,
        private null|int|Player $player2 = null,
        private ?int            $current_player = null,
        private ?int            $winner = null,
        public readonly int     $cols = 7,
        public readonly int     $rows = 6,
    ){
        if ($this->player1 instanceof Player === false ) { $this->player1 = new Player($this->player1); }
        if ($this->player2 instanceof Player === false && $this->player2 !== null) { $this->player2 = new Player($this->player1); }
    }

    /**
     * @throws ApiException
     */
    public static function create(int $playerID): self {
        $row = new BoardRepo()->create($playerID);

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
    public static function getByID(int $boardID): self {
        $row = new BoardRepo()->getBoardByID($boardID);

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
    public static function getByJoin(int $boardID, int $playerID): self {
        $row = new BoardRepo()->join($boardID, $playerID);

        return new self(
            id: $row['id'],
            player1: $row['player1'],
            player2: $row['player2'],
            current_player: $row['current_player'],
            winner: $row['winner'],
        );
    }

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

    /**
     * @throws ApiException
     */
    public function getPlayer1(): ?Player {
        return $this->player1 ? Player::getByID($this->player1) : null;
    }

    /**
     * @throws ApiException
     */
    public function getPlayer2(): ?Player {
        return $this->player2 ? Player::getByID($this->player2) : null;
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
        new TokenRepo($tokenDropRequest->getCurrentToken())->setToken();
    }

    /**
     * @throws ApiException
     */
    public function getBoardAsArray(): array
    {
        // build board['rows']['cols']
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