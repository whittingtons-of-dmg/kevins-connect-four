<?php

namespace MediumDubb\ConnectFour\Domains;

use MediumDubb\ConnectFour\Exceptions\ApiException;
use MediumDubb\ConnectFour\Exceptions\GameValidationException;
use MediumDubb\ConnectFour\Repositories\BoardRepo;
use MediumDubb\ConnectFour\Repositories\TokenRepo;
use PDOException;

final readonly class Token
{
    /**
     * @throws GameValidationException
     * @throws ApiException
     */
    public function __construct(
        private int $board,
        private int $player,
        private int $board_column,
        private ?int $board_row = null
    ){
        if ($this->getBoardColumn() < 0 || $this->getBoardColumn() >= 7)
            throw new GameValidationException("Invalid column value passed", ["Valid values range from 0 to 6"]);

        if ($this->getBoardRow() < 0 || $this->getBoardRow() >= 6)
            throw new GameValidationException("Invalid row value passed", ["Valid values range from 0 to 5"]);

        $filteredBoardID = filter_var($this->getBoardID(), FILTER_VALIDATE_INT);

        if ($filteredBoardID === false)
            throw new GameValidationException('Board ID is invalid', ['The board ID must be a positive integer']);

        try {
            if ( ! new BoardRepo()->boardExists($filteredBoardID) )
                throw new GameValidationException('Board doesn\'t exist');
        } catch (PDOException $e) {
            throw new ApiException('An error has occured. Please try again later.', 500);
        }
    }

    /**
     * @throws GameValidationException
     * @throws ApiException
     */
    public static function fromDB(array $row): self
    {
        return new self(
            board: $row['board'],
            player: $row['player'],
            board_column: $row['board_column'],
            board_row: $row['board_row']
        );
    }

    public function getBoardID(): int
    {
        return $this->board;
    }

    public function getPlayerID(): int
    {
        return $this->player;
    }

    public function getBoardColumn(): int
    {
        return $this->board_column;
    }

    /**
     * @throws ApiException
     */
    public function getBoardRow(): int
    {
        return is_int($this->board_row) ?
            $this->board_row :
            new TokenRepo($this)->getColRowCount();
    }

    public function getTokenCords(): array
    {
        return [$this->board_row, $this->board_column];
    }
}