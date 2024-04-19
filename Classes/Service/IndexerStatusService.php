<?php

namespace Tpwd\KeSearch\Service;

use Symfony\Component\Console\Style\SymfonyStyle;
use Tpwd\KeSearch\Lib\SearchHelper;
use Tpwd\KeSearch\Utility\TimeUtility;
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
    public const INDEXER_STATUS_REPORT_FORMAT_HTML = 'html';
    public const INDEXER_STATUS_REPORT_FORMAT_PLAIN = 'plain';

    private Registry $registry;
    private ?SymfonyStyle $io = null;
    private bool $progressBarStarted = false;

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
                . ' is scheduled for execution',
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

        if ($this->io && $this->progressBarStarted) {
            $this->io->progressFinish();
            $this->progressBarStarted = false;
        }
    }

    public function setRunningStatus(
        array $indexerConfig,
        int $currentRecordCount = -1,
        int $totalRecordCount = -1
    ): void {
        $indexerStatus = $this->getIndexerStatus();
        $oldStatus = $indexerStatus['indexers'][$indexerConfig['uid']]['status'] ?? null;
        $indexerStatus['indexers'][$indexerConfig['uid']] = [
            'status' => self::INDEXER_STATUS_RUNNING,
            'currentRecordCount' => $currentRecordCount,
            'totalRecords' => $totalRecordCount,
            'statusText' =>
                '"' . $indexerConfig['title'] . '"'
                . ' is running',
        ];
        if ($currentRecordCount >= 0 && $totalRecordCount >= 0) {
            $percentage = $totalRecordCount > 0
                ? round($currentRecordCount / $totalRecordCount * 100)
                : 0;
            $indexerStatus['indexers'][$indexerConfig['uid']]['statusText'] .=
                ' (' . $currentRecordCount . ' / ' . $totalRecordCount . ' records)'
                . ' (' . $percentage . '%)';
        }

        // To reduce the amount of database access, we only update the registry if the status was
        // not "running" before and every 100 records
        if ($oldStatus !== self::INDEXER_STATUS_RUNNING || $currentRecordCount % 100 === 0) {
            $this->setIndexerStatus($indexerStatus);
        }

        if ($this->io && $totalRecordCount > 0) {
            if (!$this->progressBarStarted) {
                $this->io->progressStart($totalRecordCount);
                $this->progressBarStarted = true;
            }
            // Assuming that "setRunningStatus" is called on each iteration we now call "progressAdvance" without
            // a number of steps to advance the progress bar by one step
            $this->io->progressAdvance();
        }
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
                'indexingTime' => $indexingTime,
            ]
        );
    }

    public function getLastRunTime(): array
    {
        return $this->registry->get(
            self::INDEXER_STATUS_REGISTRY_NAMESPACE,
            self::INDEXER_STATUS_REGISTRY_LAST_RUN_TIME
        ) ?? [];
    }

    /**
     * removes all entries from ke_search registry
     */
    public function clearAll(): void
    {
        $this->registry->removeAllByNamespace(self::INDEXER_STATUS_REGISTRY_NAMESPACE);
    }

    public function getStatusReport(string $format = self::INDEXER_STATUS_REPORT_FORMAT_HTML): string
    {
        $plain = [];
        if ($this->isRunning()) {
            $indexerStartTime = $this->getIndexerStartTime();
            $indexerStatus = $this->getIndexerStatus();
            $message = 'Indexer is running.';
            $html = '<div class="alert alert-success">' . $message . '</div>';
            $plain[] = $message;
            if ($indexerStatus['indexers'] ?? false) {
                $html .= '<div class="table-fit"><table class="table table-striped table-hover">';
                foreach ($indexerStatus['indexers'] as $singleIndexerStatus) {
                    $statusLine = htmlspecialchars($singleIndexerStatus['statusText'], ENT_QUOTES, 'UTF-8');
                    if ($singleIndexerStatus['status'] == self::INDEXER_STATUS_RUNNING) {
                        $statusLine = '<tr class="table-success"><td><strong>' . $statusLine . '</strong></td></tr>';
                    } else {
                        $statusLine = '<tr><td>' . $statusLine . '</td></tr>';
                    }
                    $html .= $statusLine;
                    $plain[] = $singleIndexerStatus['statusText'];
                }
                $html .= '</table></div>';
                $message = 'Indexer is running since ' . TimeUtility::getRunningTimeHumanReadable($indexerStartTime) . '.';
                $html .= '<div class="alert alert-notice">' . $message . '</div>';
                $plain[] = $message;
            }
        } else {
            $message = 'Indexer is idle.';
            $html = '<div class="alert alert-notice">' . $message . '</div>';
            $plain[] = $message;
        }

        if ($format == self::INDEXER_STATUS_REPORT_FORMAT_PLAIN) {
            return implode(chr(10), $plain);
        }
        return $html;
    }

    public function setConsoleIo(SymfonyStyle $io): void
    {
        $this->io = $io;
    }
}
