<?php

declare(strict_types=1);

namespace Tpwd\KeSearch\Controller;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Tpwd\KeSearch\Service\IndexerStatusService;

class IndexerStatusController
{
    private ResponseFactoryInterface $responseFactory;
    private IndexerStatusService $indexerStatusService;

    public function __construct(ResponseFactoryInterface $responseFactory, IndexerStatusService $indexerStatusService)
    {
        $this->responseFactory = $responseFactory;
        $this->indexerStatusService = $indexerStatusService;
    }

    public function getStatusAction(ServerRequestInterface $request): ResponseInterface
    {
        $result = [
            'running' => $this->indexerStatusService->isRunning(),
            'indexers' => $this->indexerStatusService->getIndexerStatus()['indexers'] ?? [],
            'html' => $this->indexerStatusService->getStatusReport(),
        ];

        $response = $this->responseFactory->createResponse()
            ->withHeader('Content-Type', 'application/json; charset=utf-8');
        $response->getBody()->write(
            json_encode($result, JSON_THROW_ON_ERROR),
        );

        return $response;
    }
}
