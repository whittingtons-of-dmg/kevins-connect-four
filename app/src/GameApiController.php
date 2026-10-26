<?php

namespace MediumDubb\ConnectFour;

use JetBrains\PhpStorm\NoReturn;
use JsonException;
use MediumDubb\ConnectFour\Domains\Player;
use MediumDubb\ConnectFour\DTO\BoardDTO;
use MediumDubb\ConnectFour\Exceptions\ApiException;
use MediumDubb\ConnectFour\Services\BoardRequestHandler;
use MediumDubb\ConnectFour\Services\SessionService;
use MediumDubb\ConnectFour\Services\TokenHandler;

class GameApiController
{
    /**
     * I'd make the refactor in this order:
     *
     * Standardize HTTP status codes and error response shapes.
     *
     * Add safe handling for unexpected exceptions and JSON encoding failures. AKA a translation layer for http ststsu codes
     * (Make a table of response codes and map each to a safe message that can be returned in the response)
     *
     * Write tests around the win-condition bug in TokenHandler.
     */

    private const string UID_KEY = 'UID';

    /**
     * @throws JsonException
     */
    public function dropToken(): void
    {
        $this->handleRequest(function():array {
            return new TokenHandler($this->getUid())->getResponseObj();
        });
    }

    /**
     * @throws JsonException
     */
    public function getBoardState(): void
    {
        $this->handleRequest(function():array {
            $board = BoardRequestHandler::fromGetterRequest();
            return BoardDTO::fromDomain($board)->toArray();
        });
    }

    /**
     * @throws JsonException
     */
    public function create(): void
    {
        $this->handleRequest(function():array {
            $board = BoardRequestHandler::boardFromCreateRequest($this->getUid());
            return BoardDTO::fromDomain($board)->toArray();
        });
    }

    /**
     * @throws JsonException
     */
    public function join(): void
    {
        $this->handleRequest(function(): array {
            $board = BoardRequestHandler::boardFromJoinRequest($this->getUid());
            return BoardDTO::fromDomain($board)->toArray();
        });
    }

    /**
     * @throws JsonException
     */
    private function handleRequest(callable $action): void
    {
        try {
            $payload = $action();
            $statusCode = 200;
        } catch (ApiException $e) {
            $statusCode = $e->getCode();

            $payload = [
                'status_code' => $statusCode,
                'error_message' => $e->getMessage(),
            ];
        }

        $this->sendJsonResponse($payload, $statusCode);
    }

    /**
     * @throws JsonException
     */
    #[NoReturn]
    private function sendJsonResponse(
        array $payload,
        int $statusCode = 200
    ): void {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($statusCode);
        echo json_encode($payload, JSON_THROW_ON_ERROR);

        exit;
    }

    /**
     * @throws ApiException
     */
    public function getUid(): ?string
    {
        $session = new SessionService();

        if (isset($_GET['newSession'])) {
            $session->clear();
        }

        if (!$uid = $session->get(self::UID_KEY)) {
            $uid = $_GET['playerID'] ?? Player::create()->getPlayerID();
            $session->set(self::UID_KEY, $uid);
        }

        return $uid;
    }
}