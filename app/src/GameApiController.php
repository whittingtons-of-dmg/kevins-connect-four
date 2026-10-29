<?php

namespace MediumDubb\ConnectFour;


use JetBrains\PhpStorm\NoReturn;
use JsonException;
use MediumDubb\ConnectFour\DTO\BoardDTO;
use MediumDubb\ConnectFour\Exceptions\ApiException;
use MediumDubb\ConnectFour\Exceptions\GameValidationException;
use MediumDubb\ConnectFour\Repositories\PlayerRepo;
use MediumDubb\ConnectFour\Services\CreateRequestHandler;
use MediumDubb\ConnectFour\Services\GetterRequestHandler;
use MediumDubb\ConnectFour\Services\JoinRequestHandler;
use MediumDubb\ConnectFour\Services\DropRequestHandler;

readonly class GameApiController
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
    public function __construct(private PDOConnector $db){}

    /**
     * @throws JsonException
     */
    public function dropToken(): void
    {
        $this->handleRequest(function():array {
            $handler = DropRequestHandler::buildRequestHandler($this->db);
            $board = $handler->getTheBoard();
            $colRow = $handler->getColumnRow();
            $token = $handler->getTheToken($colRow);
            $board = $handler->updateAndGetBoard($board, $token);
            return BoardDTO::fromDomain($board)->toArray();
        });
    }

    /**
     * @throws JsonException
     */
    public function getBoardState(): void
    {
        $this->handleRequest(function():array {
            $handler = GetterRequestHandler::buildRequestHandler($this->db);
            $board = $handler->getTheBoard();
            return BoardDTO::fromDomain($board)->toArray();
        });
    }

    /**
     * @throws JsonException
     */
    public function create(): void
    {
        $this->handleRequest(function():array {
            $handler = CreateRequestHandler::buildRequestHandler($this->db, $this->assignAndGetPlayerId());
            $board = $handler->createAndGetBoard();
            return BoardDTO::fromDomain($board)->toArray();
        });
    }

    /**
     * @throws JsonException
     */
    public function join(): void
    {
        $this->handleRequest(function(): array {
            $handler = JoinRequestHandler::buildRequestHandler($this->db, $this->assignAndGetPlayerId());
            $board = $handler->joinAndGetBoard();
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
            $statusCode = $e->getStatusCode();

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
    private function assignAndGetPlayerId(): int
    {
        $returnedId = new PlayerRepo($this->db)->insertAndGetID();

        if ($returnedId === false)
            throw new GameValidationException('Failed to assign new player ID');

        return (int) $returnedId;
    }
}