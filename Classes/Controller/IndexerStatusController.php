<?php

declare(strict_types=1);

namespace Tpwd\KeSearch\Controller;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Tpwd\KeSearch\Service\IndexerStatusService;
use Tpwd\KeSearch\Utility\TimeUtility;

class IndexerStatusController
{
    private ResponseFactoryInterface $responseFactory;
    private IndexerStatusService $indexerStatusService;

    public function __construct(ResponseFactoryInterface $responseFactory, IndexerStatusService $indexerStatusService) {
        $this->responseFactory = $responseFactory;
        $this->indexerStatusService = $indexerStatusService;
    }

    public function getStatusAction(ServerRequestInterface $request): ResponseInterface
    {
        $isRunning = $this->indexerStatusService->isRunning();
        $indexerStatus = [];
        $html = '<div class="alert alert-notice">Indexer is idle.</div>';

        if ($isRunning) {
            $indexerStartTime = $this->indexerStatusService->getIndexerStartTime();
            $indexerStatus = $this->indexerStatusService->getIndexerStatus();
            $html = '<div class="alert alert-success">Indexer is running.</div>';
            if ($indexerStatus['indexers'] ?? false) {
                $html .= '<div class="table-fit"><table class="table table-striped table-hover">';
                foreach ($indexerStatus['indexers'] as $singleIndexerStatus) {
                    $statusLine = htmlspecialchars($singleIndexerStatus['statusText'], ENT_QUOTES, 'UTF-8');
                    if ($singleIndexerStatus['status'] == $this->indexerStatusService::INDEXER_STATUS_RUNNING) {
                        $statusLine = '<tr class="table-success"><td><strong>' . $statusLine . '</strong></td></tr>';
                    } else {
                        $statusLine = '<tr><td>' . $statusLine . '</td></tr>';
                    }
                    $html .= $statusLine;
                }
                $html .= '</table></div>';
                $html .= '<div class="alert alert-notice">Indexer is running since ' . TimeUtility::getRunningTimeHumanReadable($indexerStartTime) . '.' . '</div>';
            }
        }

        $result = [
            'running' => $isRunning,
            'indexers' => $indexerStatus['indexers'] ?? [],
            'html' => $html,
        ];

        $response = $this->responseFactory->createResponse()
            ->withHeader('Content-Type', 'application/json; charset=utf-8');
        $response->getBody()->write(
            json_encode($result, JSON_THROW_ON_ERROR),
        );

        return $response;
    }

}
