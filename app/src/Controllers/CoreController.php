<?php

namespace MediumDubb\ConnectFour\Controllers;

use MediumDubb\ConnectFour\Database\PDOConnector;
use MediumDubb\ConnectFour\Domains\Player;
use MediumDubb\ConnectFour\Exceptions\ApiException;
use MediumDubb\ConnectFour\Repositories\BoardRepo;
use MediumDubb\ConnectFour\Services\SessionService;

class CoreController
{
    private const string UID_KEY = 'UID';

    private SessionService $session;

    protected PDOConnector $db;

    public function __construct()
    {
        $this->session = new SessionService();
        $this->db = new PDOConnector();
    }

    /**
     * @throws ApiException
     */
    public function getUid(): ?string
    {
        if (isset($_GET['newSession'])) {
            $this->session->clear();
        }

        if (!$uid = $this->session->get(self::UID_KEY)) {
            $uid = $_GET['playerID'] ?? Player::create()->getPlayerID();
            $this->session->set(self::UID_KEY, $uid);
        }

        return $uid;
    }

    public function setUid(string $id): SessionService
    {
        return $this->session->set(self::UID_KEY, $id);
    }

    public function clearUid(): void
    {
        $this->session->clear();
    }

    protected function getBaseURI(): string
    {
        return $_SERVER['REQUEST_SCHEME'] . "://" . $_SERVER['SERVER_NAME'] . "/";
    }

    /**
     * @throws ApiException
     */
    protected function validatePlayerSession(string $room_id): bool
    {
        if ($session_uid = $this->getUid()) {
            $board_users = new BoardRepo()->getRoomPlayerIDs($room_id);
            if (is_array($board_users)) {
                return in_array($session_uid, $board_users);
            }
        }

        return false;
    }
}