<?php

namespace MediumDubb\ConnectFour\Domains;

final readonly class Player
{
    public function __construct(private ?int $id = null){}

    public static function fromDB(array $row): self {
        return new self(
            id: $row['id'],
        );
    }

    public function getPlayerID(): int
    {
        return $this->id;
    }
}