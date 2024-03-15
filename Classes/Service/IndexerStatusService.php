<?php

namespace Tpwd\KeSearch\Service;

use Tpwd\KeSearch\Lib\SearchHelper;
use TYPO3\CMS\Core\Registry;

class IndexerStatusService
{
    public const INDEXER_STATUS_REGISTRY_NAMESPACE = 'tx_kesearch';
    public const INDEXER_STATUS_REGISTRY_STATUS_KEY = 'indexer-status';
    public const INDEXER_STATUS_REGISTRY_START_TIME_KEY = 'startTimeOfIndexer';
    public const INDEXER_STATUS_REGISTRY_LAST_RUN_TIME = 'lastRun';
    public const INDEXER_STATUS_SCHEDULED = 0;
    public const INDEXER_STATUS_RUNNING = 1;
    public const INDEXER_STATUS_FINISHED = 2;

    private Registry $registry;

    public function __construct(Registry $registry)
    {
        $this->registry = $registry;
    }

    public function isRunning(): bool
    {
        return (bool)SearchHelper::getIndexerStartTime();
    }

    public function getIndexerStartTime(): int
    {
        return SearchHelper::getIndexerStartTime();
    }

    public function startIndexerTime(): void
    {
        $this->registry->set(
            self::INDEXER_STATUS_REGISTRY_NAMESPACE,
            self::INDEXER_STATUS_REGISTRY_START_TIME_KEY,
            time()
        );
    }

    public function clearIndexerStartTime(): void
    {
        $this->registry->remove(
            self::INDEXER_STATUS_REGISTRY_NAMESPACE,
            self::INDEXER_STATUS_REGISTRY_START_TIME_KEY
        );
    }

    public function setScheduledStatus(array $indexerConfig)
    {
        $indexerStatus = $this->getIndexerStatus();
        $indexerStatus['indexers'][$indexerConfig['uid']] = [
            'status' => self::INDEXER_STATUS_SCHEDULED,
            'currentRecordCount' => 0,
            'totalRecords' => 0,
            'statusText' =>
                '"' . $indexerConfig['title'] . '"'
                . ' is scheduled for execution'
        ];
        $this->setIndexerStatus($indexerStatus);
    }

    public function setFinishedStatus(array $indexerConfig)
    {
        $indexerStatus = $this->getIndexerStatus();
        $indexerStatus['indexers'][$indexerConfig['uid']]['status']
            = self::INDEXER_STATUS_FINISHED;
        $indexerStatus['indexers'][$indexerConfig['uid']]['statusText']
            = '"' . $indexerConfig['title'] . '"'
            . ' has finished';
        if ($indexerStatus['indexers'][$indexerConfig['uid']]['totalRecords'] >= 0) {
            $indexerStatus['indexers'][$indexerConfig['uid']]['statusText'] .=
                ' (' . $indexerStatus['indexers'][$indexerConfig['uid']]['totalRecords'] . ' records)';
        }
        $this->setIndexerStatus($indexerStatus);
    }

    public function setRunningStatus(array $indexerConfig, int $currentRecordCount = -1, int $totalRecordCount = -1)
    {
        $indexerStatus = $this->getIndexerStatus();
        $indexerStatus['indexers'][$indexerConfig['uid']] = [
            'status' => self::INDEXER_STATUS_RUNNING,
            'currentRecordCount' => $currentRecordCount,
            'totalRecords' => $totalRecordCount,
            'statusText' =>
                '"' . $indexerConfig['title'] . '"'
                . ' is running'
                . ' (' . $currentRecordCount . ' / ' . $totalRecordCount . ' records)'
        ];
        $this->setIndexerStatus($indexerStatus);
    }

    public function getIndexerStatus(): array
    {
        return $this->registry->get(
            self::INDEXER_STATUS_REGISTRY_NAMESPACE,
            self::INDEXER_STATUS_REGISTRY_STATUS_KEY
        ) ?? [];
    }

    public function setIndexerStatus(array $indexerStatus): void
    {
        $this->registry->set(
            self::INDEXER_STATUS_REGISTRY_NAMESPACE,
            self::INDEXER_STATUS_REGISTRY_STATUS_KEY,
            $indexerStatus
        );
    }

    public function setLastRunTime(int $startTime, int $endTime, int $indexingTime): void
    {
        $this->registry->set(
            self::INDEXER_STATUS_REGISTRY_NAMESPACE,
            self::INDEXER_STATUS_REGISTRY_LAST_RUN_TIME,
            [
                'startTime' => $startTime,
                'endTime' => $endTime,
                'indexingTime' => $indexingTime
            ]
        );
    }

    /**
     * removes all entries from ke_search registry
     *
     * @return void
     */
    public function clearAll(): void
    {
        $this->registry->removeAllByNamespace(self::INDEXER_STATUS_REGISTRY_NAMESPACE);
    }
}
