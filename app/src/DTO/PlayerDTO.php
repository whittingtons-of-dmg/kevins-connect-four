<?php

namespace MediumDubb\ConnectFour\DTO;

use MediumDubb\ConnectFour\Domains\Player;

class PlayerDTO
{
    public function __construct(
        public int $id
    ) {}

    public static function fromDomain(Player $player): ?self
    {
        return new self(
            id: $player->getPlayerID(),
        );
    }

    public function toArray(): array
    {
        return [
            'id'    => $this->id,
        ];
    }
}