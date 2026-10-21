<?php

namespace MediumDubb\ConnectFour\Controllers;

use JetBrains\PhpStorm\NoReturn;
use MediumDubb\ConnectFour\Domains\Board;
use MediumDubb\ConnectFour\DTO\BoardResponse;
use MediumDubb\ConnectFour\Exceptions\ApiException;
use MediumDubb\ConnectFour\Services\BoardCreateRequest;
use MediumDubb\ConnectFour\Services\BoardJoinRequest;
use MediumDubb\ConnectFour\Services\BoardStateRequest;
use MediumDubb\ConnectFour\Services\TokenHandler;

class GameApiController extends CoreController
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

    public function dropToken(): void
    {
        $this->handleRequest(function():array {
            return new TokenHandler($this->getUid())->getResponseObj();
        });
    }

    // used for all responses
    public function getBoardState(): void
    {
        $this->handleRequest(function():array {
            $stateRequest = BoardStateRequest::fromQueryParams();
            $board = Board::getByID($stateRequest->getBoardID());
            return BoardResponse::fromDomain($board)->toArray();
        });
    }

    public function create(): void
    {
        $this->handleRequest(function():array {
            $createRequest = BoardCreateRequest::validateRequestMethod($this->getUid());
            $board = Board::create($createRequest->getPlayerID());
            return BoardResponse::fromDomain($board)->toArray();
        });
    }

    public function join(): void
    {
        $this->handleRequest(function(): array {
            $joinRequest = BoardJoinRequest::fromQueryParams();
            $board = Board::getByJoin($joinRequest->getBoardID(), $this->getUid());
            return  BoardResponse::fromDomain($board)->toArray();
        });
    }

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
}