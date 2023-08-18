<?php

/** @noinspection SqlNoDataSourceInspection */

namespace Tpwd\KeSearch\Indexer;

/***************************************************************
 *  Copyright notice
 *  (c) 2010 Andreas Kiefer
 *  All rights reserved
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use Exception;
use PDO;
use Psr\EventDispatcher\EventDispatcherInterface;
use Tpwd\KeSearch\Domain\Repository\IndexRepository;
use Tpwd\KeSearch\Event\ModifyFieldValuesBeforeStoringEvent;
use Tpwd\KeSearch\Lib\Db;
use Tpwd\KeSearch\Lib\SearchHelper;
use TYPO3\CMS\Core\Log\Logger;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Mail\MailMessage;
use TYPO3\CMS\Core\Registry;
use TYPO3\CMS\Core\Utility\DebugUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\StringUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Plugin 'Faceted search' for the 'ke_search' extension.
 * @author    Andreas Kiefer
 * @author    Stefan Froemken
 * @author    Christian Bülter
 */
class IndexerRunner
{
    public $counter;
    public $extConf; // extension configuration
    public $extConfPremium = []; // extension configuration of ke_search_premium, if installed
    public $indexerConfig = []; // saves the indexer configuration of current loop
    public $additionalFields = [];
    public $indexingErrors = [];

    /**
     * @var int
     */
    public $startTime = 0;

    /**
     * @var int
     */
    public $endTime = 0;

    /**
     * current row which have to be inserted/updated to database
     * @var array
     */
    public $currentRow = [];

    /**
     * @var Registry
     */
    public $registry;

    /**
     * @var Logger
     */
    public $logger;

    /**
     * @var array
     */
    public $defaultIndexerTypes = [];

    private EventDispatcherInterface $eventDispatcher;
    private IndexRepository $indexRepository;

    /**
     * Constructor of this class
     */
    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        IndexRepository $indexRepository
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->indexRepository = $indexRepository;

        // get extension configuration array
        $this->extConf = SearchHelper::getExtConf();
        $this->extConfPremium = SearchHelper::getExtConfPremium();
        $this->registry = GeneralUtility::makeInstance(Registry::class);

        // fetch the list of the default indexers which come with ke_search
        foreach ($GLOBALS['TCA']['tx_kesearch_indexerconfig']['columns']['type']['config']['items'] as $indexerType) {
            $this->defaultIndexerTypes[] = $indexerType['value'] ?? $indexerType[1];
        }

        // init logger
        $this->logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
    }

    /**
     * function startIndexing
     * @param $verbose boolean if set, information about the indexing process is returned, otherwise processing is quiet
     * @param $extConf array extension config array from EXT Manager
     * @param $mode string "CLI" if called from command line, otherwise empty
     * @param int $indexingMode integer full or incremental indexing (possible values: IndexerBase::INDEXING_MODE_FULL or IndexerBase::INDEXING_MODE_INCREMENTAL)
     * @return string output is done only if param $verbose is true
     */
    public function startIndexing($verbose = true, $extConf = [], $mode = '', $indexingMode = IndexerBase::INDEXING_MODE_FULL)
    {
        $content = '<div class="row"><div class="col-md-8">';
        $content .= '<div class="alert alert-info">';
        $message = 'Running indexing process in '
            . LocalizationUtility::translate('backend.indexingMode_' . $indexingMode, 'ke_search')
            . ' mode';
        if ($indexingMode == IndexerBase::INDEXING_MODE_INCREMENTAL) {
            if (SearchHelper::getIndexerLastRunTime() == 0) {
                $message .= ', but last run time is not available. Switching to full mode.';
                $indexingMode = IndexerBase::INDEXING_MODE_FULL;
            } else {
                $message .= ', last run was ' . SearchHelper::formatTimestamp(SearchHelper::getIndexerLastRunTime());
            }
        }
        $message .= '.';
        $content .= $message;
        $this->logger->notice('Indexing process started: ' . $message);
        $content .= '</div>';

        // write starting timestamp into registry
        // this is a helper to delete all records which are older than starting timestamp in registry
        // this also prevents starting the indexer twice
        if ($this->registry->get('tx_kesearch', 'startTimeOfIndexer') === null) {
            $this->registry->set('tx_kesearch', 'startTimeOfIndexer', time());
        } else {
            // check lock time
            $lockTime = $this->registry->get('tx_kesearch', 'startTimeOfIndexer');
            $compareTime = time() - (60 * 60 * 12);
            if ($lockTime < $compareTime) {
                // lock is older than 12 hours - remove
                $this->registry->remove('tx_kesearch', 'startTimeOfIndexer');
                $this->registry->set('tx_kesearch', 'startTimeOfIndexer', time());
                $this->logger->notice('lock has been removed because it is older than 12 hours' . time());
            } else {
                $this->logger->warning('lock is set, you can\'t start indexer twice.');
                return 'You can\'t start the indexer twice. Please wait '
                    . 'while first indexer process is currently running';
            }
        }

        // set indexing start time
        $this->startTime = time();

        // get configurations
        $configurations = $this->getConfigurations();

        // register additional fields which should be written to DB
        if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['registerAdditionalFields'] ?? null)) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['registerAdditionalFields'] as $_classRef) {
                $_procObj = GeneralUtility::makeInstance($_classRef);
                $_procObj->registerAdditionalFields($this->additionalFields);
            }
        }

        // set some prepare statements
        $this->prepareStatements();

        $content .= '<div class="table-fit"><table class="table table-striped table-hover">';
        $content .= '<tr><th>Indexer</th><th>Mode</th><th>Info</th><th>Time</th></tr>';
        foreach ($configurations as $indexerConfig) {
            $this->indexerConfig = $indexerConfig;

            // run default indexers shipped with ke_search
            if (in_array($this->indexerConfig['type'], $this->defaultIndexerTypes)) {
                $className = __NAMESPACE__ . '\\Types\\';
                $className .= GeneralUtility::underscoredToUpperCamelCase($this->indexerConfig['type']);
                if (class_exists($className)) {
                    $this->logger->info(
                        'Indexer "' . $this->indexerConfig['title'] . '" started ',
                        $this->indexerConfig
                    );
                    $searchObj = GeneralUtility::makeInstance($className, $this);
                    if ($indexingMode == IndexerBase::INDEXING_MODE_FULL) {
                        $message = $searchObj->startIndexing();
                    } else {
                        if (method_exists($searchObj, 'startIncrementalIndexing')) {
                            $message = $searchObj->startIncrementalIndexing();
                        } else {
                            $message = 'Incremental indexing is not available for this indexer, starting full indexing. <br />';
                            $this->logger->info(strip_tags($message));
                            $message .= $searchObj->startIndexing();
                        }
                    }
                    $content .= $this->renderIndexingReport($searchObj, $message);
                } else {
                    $errorMessage = 'Could not find class ' . $className;
                    $this->logger->error($errorMessage);
                    $content .= '<div class="alert alert-error">' . $errorMessage . '</div>' . "\n";
                }
            }

            // hook for custom indexer
            if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['customIndexer'] ?? null)) {
                foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['customIndexer'] as $_classRef) {
                    $searchObj = GeneralUtility::makeInstance($_classRef, $this);
                    $this->logger->info(
                        'Custom indexer "' . $this->indexerConfig['title'] . '" started',
                        $this->indexerConfig
                    );
                    if ($indexingMode == IndexerBase::INDEXING_MODE_FULL || !method_exists($searchObj, 'startIncrementalIndexing')) {
                        $message = $searchObj->customIndexer($indexerConfig, $this);
                    } else {
                        $message = $searchObj->startIncrementalIndexing($indexerConfig, $this);
                    }
                    if ($message) {
                        $content .= $this->renderIndexingReport($searchObj, $message);
                    }
                }
            }
        }
        $content .= '</table></div>';

        // process index cleanup
        $content .= $this->cleanUpIndex($indexingMode);

        // clean up process after indexing to free memory
        $this->cleanUpProcessAfterIndexing();

        // set indexing end time
        $this->endTime = time();

        // log finishing
        $indexingTime = $this->endTime - $this->startTime;
        $content .= '<div class="alert alert-info">';

        $message = 'Indexing finished at ' . SearchHelper::formatTimestamp($this->endTime) . ' (took ' . $this->formatTime($indexingTime) . ').';
        $content .=  '<p>' . $message . '</p>';
        $this->logger->info($message);

        $message = 'Index contains ' . $this->indexRepository->getTotalNumberOfRecords() . ' entries.';
        $content .=  '<p>' . $message . '</p>';
        $this->logger->info($message);

        $content .= '</div>';
        $content .= '</div></div>';

        $this->registry->set(
            'tx_kesearch',
            'lastRun',
            ['startTime' => $this->startTime, 'endTime' => $this->endTime, 'indexingTime' => $indexingTime]
        );

        // create plaintext report
        $plaintextReport = $this->createPlaintextReport($content);

        // send notification in CLI mode
        if ($mode == 'CLI') {
            // send finishNotification
            $isValidEmail = GeneralUtility::validEmail($this->extConf['notificationRecipient']);
            if ($this->extConf['finishNotification'] && $isValidEmail) {
                // send the notification message
                /** @var MailMessage $mail */
                $mail = GeneralUtility::makeInstance(MailMessage::class);
                $mail->setFrom([$this->extConf['notificationSender']]);
                $mail->setTo([$this->extConf['notificationRecipient']]);
                $mail->setSubject($this->extConf['notificationSubject']);
                $mail->text($plaintextReport);
                $mail->send();
            }
        }

        // Log report to sys_log and decode urls to prevent errors in backend module,
        // make sure report fits into the 'details' column of sys_log which is of type "text" and can hold 64 KB.
        $GLOBALS['BE_USER']->writelog(
            4,
            0,
            0,
            -1,
            '[ke_search] ' . urldecode(html_entity_decode(substr($plaintextReport, 0, 60000))),
            []
        );

        // verbose or quiet output? as set in function call!
        if ($verbose) {
            return $content;
        }

        return '';
    }

    /**
     * Renders the message from the indexers.
     *
     * @param object $searchObj Indexer Object (should extend IndexerBase, but this may not be the case)
     * @param string $message
     * @return string
     */
    public function renderIndexingReport($searchObj, $message = '')
    {
        $content = '<tr>';

        // title
        if (!empty($searchObj->indexerConfig['title'])) {
            $title = $searchObj->indexerConfig['title'];
        } else {
            $title = get_class($searchObj);
        }
        $content .= '<td><span class="title">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</span></td>';

        // indexing mode
        $content .= '<td>';
        if (is_subclass_of($searchObj, '\Tpwd\KeSearch\Indexer\IndexerBase')) {
            if (method_exists($searchObj, 'getIndexingMode')) {
                if ($searchObj->getIndexingMode() == IndexerBase::INDEXING_MODE_INCREMENTAL) {
                    $content .= '<span class="indexingMode">Incremental mode</span>';
                }
            }
        }
        $content .= '</td>';

        // message
        $message = str_ireplace(['<br />', '<br>', '<br/>', '</span>'], "\n", $message);
        $message = strip_tags($message);
        $content .= '<td>';
        $content .= nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));

        // errors
        if (is_subclass_of($searchObj, '\Tpwd\KeSearch\Indexer\IndexerBase')) {
            $errors = method_exists($searchObj, 'getErrors') ? $searchObj->getErrors() : [];
            if (count($errors)) {
                $content .= '<p class="badge badge-warning">Warning: There have been errors. Please refer to the error log (typically in var/log/)</p>';
            }
        }

        $content .= '</td>';

        // duration, show sec or ms
        $content .= '<td>';
        if (is_subclass_of($searchObj, '\Tpwd\KeSearch\Indexer\IndexerBase')) {
            $duration = method_exists($searchObj, 'getDuration') ? $searchObj->getDuration() : 0;
            if ($duration > 0) {
                $content .= '<i>Indexing process took ';
                if ($duration > 1000) {
                    $duration /= 1000;
                    $duration = (int)$duration;
                    $content .= $duration . ' s.';
                } else {
                    $content .= $duration . ' ms.';
                }
                $content .= '</i>';
            }
        }
        $content .= '</td>';

        $content .= '</tr>';
        return $content;
    }

    /**
     * create plaintext report from html content
     * @param string $content
     * @return string
     */
    public function createPlaintextReport($content)
    {
        $content = str_ireplace(['<span class="title">', '<br />', '<br>', '<br/>', '</span>', '</p>'], chr(10), $content);
        $report = preg_replace('~[ ]{2,}~', '', strip_tags($content));
        return $report;
    }

    /**
     * create human readable string for indexing time
     *
     * @param $time int Indexing time in seconds
     * @return float|int|string
     */
    protected function formatTime($time)
    {
        if ($time > 3600) {
            // format hours
            $time = $time / 3600;
            $time = number_format($time, 2, ',', '.');
            $time .= ' hours';
        } else {
            if ($time > 60) {
                // format minutes
                $time = $time / 60;
                $time = number_format($time, 2, ',', '.');
                $time .= ' minutes';
            } else {
                $time .= ' seconds';
            }
        }

        return $time;
    }

    /**
     * prepare sql-statements for indexer
     */
    public function prepareStatements()
    {
        $addUpdateQuery = '';
        $addInsertQueryFields = '';
        $addInsertQueryValues = '';

        // create vars to keep statements dynamic
        foreach ($this->additionalFields as $value) {
            $addUpdateQuery .= ', ' . $value . ' = ?';
            $addInsertQueryFields .= ', ' . $value;
            $addInsertQueryValues .= ', ?';
        }

        // Statement to check if record already exists in db
        $databaseConnection = Db::getDatabaseConnection('tx_kesearch_index');
        $databaseConnection->exec('PREPARE searchStmt FROM "
			SELECT *
			FROM tx_kesearch_index
			WHERE orig_uid = ?
			AND pid = ?
			AND type = ?
			AND language = ?
			LIMIT 1
		"');

        // Statement to update an existing record in indexer table
        $databaseConnection = Db::getDatabaseConnection('tx_kesearch_index');
        $databaseConnection->exec('PREPARE updateStmt FROM "
			UPDATE tx_kesearch_index
			SET pid=?,
			title=?,
			type=?,
			targetpid=?,
			content=?,
			tags=?,
			params=?,
			abstract=?,
			language=?,
			starttime=?,
			endtime=?,
			fe_group=?,
			tstamp=?' . $addUpdateQuery . '
			WHERE uid=?
		"');

        // Statement to insert a new records to index table
        $databaseConnection = Db::getDatabaseConnection('tx_kesearch_index');
        $databaseConnection->exec('PREPARE insertStmt FROM "
			INSERT INTO tx_kesearch_index
			(pid, title, type, targetpid, content, tags, params, abstract, language,'
            . ' starttime, endtime, fe_group, tstamp, crdate' . $addInsertQueryFields . ')
			VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?' . $addInsertQueryValues . ', ?)
		"');

        // disable keys only if indexer table was truncated (has 0 records)
        // this speeds up the first indexing process
        // don't use this for updating index table
        // if you activate this for updating 40.000 existing records, indexing process needs 1 hour longer
        if ($this->indexRepository->getTotalNumberOfRecords() == 0) {
            Db::getDatabaseConnection('tx_kesearch_index')->exec('ALTER TABLE tx_kesearch_index DISABLE KEYS');
        }
    }

    /**
     * clean up statements
     */
    public function cleanUpProcessAfterIndexing()
    {
        // enable keys (may have been disabled because it was the first indexing)
        Db::getDatabaseConnection('tx_kesearch_index')
            ->exec('ALTER TABLE tx_kesearch_index ENABLE KEYS');

        Db::getDatabaseConnection('tx_kesearch_index')
            ->exec('DEALLOCATE PREPARE searchStmt');

        Db::getDatabaseConnection('tx_kesearch_index')
            ->exec('DEALLOCATE PREPARE updateStmt');

        Db::getDatabaseConnection('tx_kesearch_index')
            ->exec('DEALLOCATE PREPARE insertStmt');

        // remove all entries from ke_search registry
        $this->registry->removeAllByNamespace('tx_kesearch');
    }

    /**
     * Delete all index elements that are older than starting timestamp in registry
     * @return string content for BE
     */
    public function cleanUpIndex(int $indexingMode)
    {
        $content = '<div class="alert alert-info">';
        $content .= '<h3>Cleanup</h3>';
        if ($indexingMode == IndexerBase::INDEXING_MODE_FULL) {
            $this->logger->info('Cleanup started');
            $startMicrotime = microtime(true);
            $table = 'tx_kesearch_index';

            // select all index records older than the beginning of the indexing process
            $queryBuilder = Db::getQueryBuilder('tx_kesearch_index');
            $where = $queryBuilder->expr()->lt(
                'tstamp',
                $queryBuilder->quote(
                    $this->registry->get('tx_kesearch', 'startTimeOfIndexer'),
                    PDO::PARAM_INT
                )
            );

            // hook for cleanup
            if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['cleanup'] ?? null)) {
                foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['cleanup'] as $_classRef) {
                    $_procObj = GeneralUtility::makeInstance($_classRef);
                    $content .= $_procObj->cleanup($where, $this);
                }
            }

            // count and delete old index records
            $count = $queryBuilder
                ->count('*')
                ->from($table)
                ->where($where)
                ->executeQuery()
                ->fetchNumeric()[0];

            $queryBuilder
                ->delete($table)
                ->where($where)
                ->executeStatement();

            $content .= '<p><strong>' . $count . '</strong> entries deleted.</p>' . "\n";
            $this->logger->info('CleanUpIndex: ' . $count . ' entries deleted.');

            // rotate Sphinx Index (ke_search_premium function)
            $content .= $this->rotateSphinxIndex();

            // calculate duration of indexing process
            $duration = ceil((microtime(true) - $startMicrotime) * 1000);
            $content .= '<p><i>Cleanup process took ' . $duration . ' ms.</i></p>' . "\n";
        } else {
            $message = 'Skipping cleanup in incremental mode';
            $this->logger->info($message);
            $content .= '<p>' . $message . '</p>';
        }
        $content .= '</div>';
        return $content;
    }

    /**
     * updates the sphinx index
     * @return string
     */
    public function rotateSphinxIndex()
    {
        $content = '';

        // check if Sphinx is enabled
        // in this case we have to update sphinx index, too.
        if ($this->extConfPremium['enableSphinxSearch'] ?? false) {
            $this->logger->info('Sphinx index rotation started');
            if (!$this->extConfPremium['sphinxIndexerName']) {
                $this->extConfPremium['sphinxIndexerConf'] = '--all';
            }
            if (is_file($this->extConfPremium['sphinxIndexerPath'])
                && is_executable($this->extConfPremium['sphinxIndexerPath'])
                && file_exists($this->extConfPremium['sphinxSearchdPath'])) {
                if (function_exists('exec')) {
                    // check if daemon is running
                    $content .= '<p>';
                    $retArr = [];
                    exec($this->extConfPremium['sphinxSearchdPath'] . ' --status', $retArr);
                    $content .= '<b>Checking status of Sphinx daemon:</b> ';
                    $sphinxFailedToConnect = false;
                    foreach ($retArr as $retRow) {
                        if (strpos($retRow, 'WARNING') !== false) {
                            $this->logger->warning('Sphinx: ' . $retRow);
                            $content .= '<div class="error">SPHINX ' . $retRow . '</div>' . "\n";
                            $sphinxFailedToConnect = true;
                        }
                    }

                    // try to start the sphinx daemon
                    if ($sphinxFailedToConnect) {
                        $retArr = [];
                        exec($this->extConfPremium['sphinxSearchdPath'], $retArr);
                        $this->logger->info('Sphinx: Trying to start deamon');
                        $content .= '<p><b>Trying to start Sphinx daemon.</b><br />'
                            . implode('<br />', $retArr)
                            . '</p>'
                            . "\n";
                    } else {
                        $content .= 'OK';
                    }
                    $content .= '</p>' . "\n";

                    // update the index
                    $retArr = [];
                    exec(
                        $this->extConfPremium['sphinxIndexerPath']
                        . ' --rotate '
                        . $this->extConfPremium['sphinxIndexerName'],
                        $retArr
                    );
                    $this->logger->warning('Sphinx: Creating new index (rotating)');
                    $content .= '<p><b>Creating new Sphinx index (rotating).</b><br />'
                        . "\n"
                        . implode('<br />' . "\n", $retArr)
                        . '</p>'
                        . "\n\n";
                    foreach ($retArr as $retRow) {
                        if (strpos($retRow, 'WARNING') !== false) {
                            $this->logger->error('Sphinx: ' . $retRow);
                            $content .= '<div class="error">SPHINX ' . $retRow . '</div>' . "\n";
                        }
                    }
                } else {
                    $this->logger->error('Sphinx: "exec" call is not allowed. '
                        . 'Check your disable_functions setting in php.ini');
                    $content .= '<div class="error">SPHINX ERROR: "exec" call is not allowed. '
                        . 'Check your disable_functions setting in php.ini.</div>';
                }
            } else {
                $this->logger->error('Sphinx: Executables not found or execution permission missing.');
                $content .= '<div class="error">SPHINX ERROR: Sphinx executables '
                    . 'not found or execution permission is missing.</div>';
            }
        }

        return $content;
    }

    /**
     * store collected data of defined indexers to db
     * @param int $storagePid
     * @param string $title
     * @param string $type
     * @param string $targetPid
     * @param string $content
     * @param string $tags
     * @param string $params
     * @param string $abstract
     * @param int $language
     * @param int $starttime
     * @param int $endtime
     * @param string $fe_group
     * @param bool $debugOnly
     * @param array $additionalFields
     * @return bool|int
     */
    public function storeInIndex(
        $storagePid,
        $title,
        $type,
        $targetPid,
        $content,
        $tags = '',
        $params = '',
        $abstract = '',
        $language = 0,
        $starttime = 0,
        $endtime = 0,
        $fe_group = '',
        $debugOnly = false,
        $additionalFields = []
    ) {
        // if there are errors found in current record return false and break processing
        if (!$this->checkIfRecordHasErrorsBeforeIndexing($storagePid, $title, $type, $targetPid)) {
            return false;
        }

        // optionally add tag set in the indexer configuration
        if (!empty($this->indexerConfig['filteroption'])
            && (
                (substr($type, 0, 4) != 'file'
                    || (substr($type, 0, 4) == 'file' && $this->indexerConfig['index_use_page_tags_for_files']))
                || $this->indexerConfig['type'] == 'file'
            )
        ) {
            $indexerTag = $this->getTag($this->indexerConfig['filteroption']);
            $tagChar = $this->extConf['prePostTagChar'];
            if ($tags) {
                $tags .= ',' . $tagChar . $indexerTag . $tagChar;
            } else {
                $tags = $tagChar . $indexerTag . $tagChar;
            }
            $tags = StringUtility::uniqueList($tags);
        }

        $table = 'tx_kesearch_index';
        $fieldValues = $this->createFieldValuesForIndexing(
            $storagePid,
            $title,
            $type,
            $targetPid,
            $content,
            $tags,
            $params,
            $abstract,
            $language,
            $starttime,
            $endtime,
            $fe_group,
            $additionalFields
        );

        // Hook to manipulate the field values before they go to the database
        if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['modifyFieldValuesBeforeStoring'] ?? null)) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['modifyFieldValuesBeforeStoring'] as $_classRef) {
                $_procObj = GeneralUtility::makeInstance($_classRef);
                $fieldValues = $_procObj->modifyFieldValuesBeforeStoring($this->indexerConfig, $fieldValues);
            }
        }

        // Event to manipulate the field values before they go to the database
        /** @var ModifyFieldValuesBeforeStoringEvent $modifyFieldValuesBeforeStoringEvent */
        $modifyFieldValuesBeforeStoringEvent = $this->eventDispatcher->dispatch(
            new ModifyFieldValuesBeforeStoringEvent(
                $this->indexerConfig,
                $fieldValues
            )
        );
        $fieldValues = $modifyFieldValuesBeforeStoringEvent->getFieldValues();

        // check if record already exists
        if (substr($type, 0, 4) == 'file') {
            $recordExists = $this->checkIfFileWasIndexed(
                $fieldValues['type'],
                $fieldValues['hash'],
                $fieldValues['pid'],
                $fieldValues['sortdate']
            );
        } else {
            $recordExists = $this->checkIfRecordWasIndexed(
                $fieldValues['orig_uid'],
                $fieldValues['pid'],
                $fieldValues['type'],
                $fieldValues['language']
            );
        }

        if ($recordExists) { // update existing record
            $where = 'uid=' . (int)($this->currentRow['uid']);
            unset($fieldValues['crdate']);
            if ($debugOnly) { // do not process - just debug query
                DebugUtility::debug(
                    (string)Db::getDatabaseConnection($table)
                        ->update(
                            $table,
                            $fieldValues,
                            ['uid' => (int)($this->currentRow['uid'])]
                        )
                );
            } else { // process storing of index record and return true
                $this->updateRecordInIndex($fieldValues);
                return true;
            }
        } else { // insert new record
            if ($debugOnly) { // do not process - just debug query
                DebugUtility::debug(
                    (string)Db::getDatabaseConnection($table)
                        ->insert(
                            $table,
                            $fieldValues
                        )
                );
            } else { // process storing of index record and return uid
                $this->insertRecordIntoIndex($fieldValues);
                return (int)Db::getDatabaseConnection('tx_kesearch_index')->lastInsertId($table);
            }
        }
        return 0;
    }

    /**
     * inserts a new record into the index using a prepared statement
     * @param $fieldValues array
     */
    public function insertRecordIntoIndex($fieldValues)
    {
        $this->logger->debug('Inserting: ' . json_encode($this->getDebugValuesFromFieldValues($fieldValues)));
        $queryBuilder = Db::getQueryBuilder('tx_kesearch_index');
        $addQueryPartFor = $this->getQueryPartForAdditionalFields($fieldValues);

        $queryArray = [];
        $queryArray['set'] = 'SET
			@pid = ' . $queryBuilder->quote($fieldValues['pid'], PDO::PARAM_INT) . ',
			@title = ' . $queryBuilder->quote($fieldValues['title'], PDO::PARAM_STR) . ',
			@type = ' . $queryBuilder->quote($fieldValues['type'], PDO::PARAM_STR) . ',
			@targetpid = ' . $queryBuilder->quote($fieldValues['targetpid']) . ',
			@content = ' . $queryBuilder->quote($fieldValues['content'], PDO::PARAM_STR) . ',
			@tags = ' . $queryBuilder->quote($fieldValues['tags'], PDO::PARAM_STR) . ',
			@params = ' . $queryBuilder->quote($fieldValues['params'], PDO::PARAM_STR) . ',
			@abstract = ' . $queryBuilder->quote($fieldValues['abstract'], PDO::PARAM_STR) . ',
			@language = ' . $queryBuilder->quote($fieldValues['language'], PDO::PARAM_INT) . ',
			@starttime = ' . $queryBuilder->quote($fieldValues['starttime'], PDO::PARAM_INT) . ',
			@endtime = ' . $queryBuilder->quote($fieldValues['endtime'], PDO::PARAM_INT) . ',
			@fe_group = ' . $queryBuilder->quote($fieldValues['fe_group'], PDO::PARAM_INT) . ',
			@tstamp = ' . $queryBuilder->quote($fieldValues['tstamp'], PDO::PARAM_INT) . ',
			@crdate = ' . $queryBuilder->quote($fieldValues['crdate'], PDO::PARAM_INT)
            . $addQueryPartFor['set'] . '
		;';

        $queryArray['execute'] = 'EXECUTE insertStmt USING '
            . '@pid, '
            . '@title, '
            . '@type, '
            . '@targetpid, '
            . '@content, '
            . '@tags, '
            . '@params, '
            . '@abstract, '
            . '@language, '
            . '@starttime, '
            . '@endtime, '
            . '@fe_group, '
            . '@tstamp, '
            . '@crdate'
            . $addQueryPartFor['execute'] . ';';

        try {
            Db::getDatabaseConnection('tx_kesearch_index')->executeStatement($queryArray['set']);
            Db::getDatabaseConnection('tx_kesearch_index')->executeStatement($queryArray['execute']);
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
        }
    }

    /**
     * updates a record in the index using a prepared statement
     * @param $fieldValues
     */
    public function updateRecordInIndex($fieldValues)
    {
        $this->logger->debug('Updating: ' . json_encode($this->getDebugValuesFromFieldValues($fieldValues)));
        $queryBuilder = Db::getQueryBuilder('tx_kesearch_index');
        $addQueryPartFor = $this->getQueryPartForAdditionalFields($fieldValues);

        $queryArray = [];
        $queryArray['set'] = 'SET
			@pid = ' . $queryBuilder->quote($fieldValues['pid'], PDO::PARAM_INT) . ',
			@title = ' . $queryBuilder->quote($fieldValues['title'], PDO::PARAM_STR) . ',
			@type = ' . $queryBuilder->quote($fieldValues['type'], PDO::PARAM_STR) . ',
			@targetpid = ' . $queryBuilder->quote($fieldValues['targetpid']) . ',
			@content = ' . $queryBuilder->quote($fieldValues['content'], PDO::PARAM_STR) . ',
			@tags = ' . $queryBuilder->quote($fieldValues['tags'], PDO::PARAM_STR) . ',
			@params = ' . $queryBuilder->quote($fieldValues['params'], PDO::PARAM_STR) . ',
			@abstract = ' . $queryBuilder->quote($fieldValues['abstract'], PDO::PARAM_STR) . ',
			@language = ' . $queryBuilder->quote($fieldValues['language'], PDO::PARAM_INT) . ',
			@starttime = ' . $queryBuilder->quote($fieldValues['starttime'], PDO::PARAM_INT) . ',
			@endtime = ' . $queryBuilder->quote($fieldValues['endtime'], PDO::PARAM_INT) . ',
			@fe_group = ' . $queryBuilder->quote($fieldValues['fe_group'], PDO::PARAM_INT) . ',
			@tstamp = ' . $queryBuilder->quote($fieldValues['tstamp'], PDO::PARAM_INT) .
            $addQueryPartFor['set'] . ',
			@uid = ' . $this->currentRow['uid'] . '
		';

        $queryArray['execute'] = 'EXECUTE updateStmt USING '
            . '@pid, '
            . '@title, '
            . '@type, '
            . '@targetpid, '
            . '@content, '
            . '@tags, '
            . '@params, '
            . '@abstract, '
            . '@language, '
            . '@starttime, '
            . '@endtime, '
            . '@fe_group, '
            . '@tstamp'
            . $addQueryPartFor['execute']
            . ', @uid;';

        try {
            Db::getDatabaseConnection('tx_kesearch_index')->executeStatement($queryArray['set']);
            Db::getDatabaseConnection('tx_kesearch_index')->executeStatement($queryArray['execute']);
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
        }
    }

    /**
     * Return the query part for additional fields to get prepare statements dynamic
     * @param array $fieldValues
     * @return array containing two query parts
     */
    public function getQueryPartForAdditionalFields(array $fieldValues)
    {
        $queryForSet = '';
        $queryForExecute = '';

        $queryBuilder = Db::getQueryBuilder('tx_kesearch_index');

        foreach ($this->additionalFields as $value) {
            $queryForSet .= ', @' . $value . ' = ' . $queryBuilder->quote($fieldValues[$value], PDO::PARAM_STR);
            $queryForExecute .= ', @' . $value;
        }
        return ['set' => $queryForSet, 'execute' => $queryForExecute];
    }

    /**
     * try to find an already indexed record
     * This function also sets $this->currentRow
     * parameters should be already fullQuoted. see storeInIndex
     * TODO: We should create an index to column type
     * @param string $uid
     * @param int $pid
     * @param string $type
     * @param int $language
     * @return bool true if record was found, false if not
     */
    public function checkIfRecordWasIndexed($uid, $pid, $type, $language)
    {
        $queryBuilder = Db::getQueryBuilder('tx_kesearch_index');
        $res = $queryBuilder
            ->select('*')
            ->from('tx_kesearch_index')
            ->where(
                $queryBuilder->expr()->eq('orig_uid', $queryBuilder->quote($uid, PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('pid', $queryBuilder->quote($pid, PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('type', $queryBuilder->quote($type, PDO::PARAM_STR)),
                $queryBuilder->expr()->eq('language', $queryBuilder->quote($language, PDO::PARAM_INT))
            )
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAllAssociative();

        if (count($res)) {
            if ($this->currentRow = reset($res)) {
                return true;
            }
            $this->currentRow = [];
            return false;
        }
        $this->currentRow = [];
        return false;
    }

    /**
     * try to find an already indexed record
     * This function also sets $this->currentRow
     * parameters should be already fullQuoted. see storeInIndex
     * TODO: We should create an index to column type
     * @param string $type
     * @param string $hash
     * @param int $pid
     * @param int $sortdate contains the file modification time
     * @return bool true if record was found, false if not
     */
    public function checkIfFileWasIndexed(string $type, string $hash, int $pid, int $sortdate): bool
    {
        // Query DB if record already exists
        $queryBuilder = Db::getQueryBuilder('tx_kesearch_index');
        $res = $queryBuilder
            ->select('*')
            ->from('tx_kesearch_index')
            ->where(
                $queryBuilder->expr()->eq(
                    'type',
                    $queryBuilder->quote($type, PDO::PARAM_STR)
                ),
                $queryBuilder->expr()->eq(
                    'hash',
                    $queryBuilder->quote($hash, PDO::PARAM_STR)
                ),
                $queryBuilder->expr()->eq(
                    'pid',
                    $queryBuilder->quote($pid, PDO::PARAM_INT)
                ),
                $queryBuilder->expr()->eq(
                    'sortdate',
                    $queryBuilder->quote($sortdate, PDO::PARAM_INT)
                )
            )
            ->executeQuery();

        if ($res->rowCount()) {
            if ($this->currentRow = $res->fetchAssociative()) {
                return true;
            }
            $this->currentRow = [];
            return false;
        }
        $this->currentRow = [];
        return false;
    }

    /**
     * Create fieldValues to save them in db later on
     * sets some default values, too
     * @param int $storagepid
     * @param string $title
     * @param string $type
     * @param string $targetpid
     * @param string $content
     * @param string $tags
     * @param string $params
     * @param string $abstract
     * @param int $language
     * @param int $starttime
     * @param int $endtime
     * @param string $fe_group
     * @param array $additionalFields
     * @return array
     */
    public function createFieldValuesForIndexing(
        $storagepid,
        $title,
        $type,
        $targetpid,
        $content,
        $tags = '',
        $params = '',
        $abstract = '',
        $language = 0,
        $starttime = 0,
        $endtime = 0,
        $fe_group = '',
        $additionalFields = []
    ): array {
        $now = time();
        $fieldsValues = [
            'pid' => (int)$storagepid,
            'title' => $this->stripControlCharacters($title),
            'type' => $type,
            'targetpid' => $targetpid,
            'content' => $this->stripControlCharacters($content),
            'tags' => $tags,
            'params' => $params,
            'abstract' => $this->stripControlCharacters($abstract),
            'language' => (int)$language,
            'starttime' => (int)$starttime,
            'endtime' => (int)$endtime,
            'fe_group' => $fe_group,
            'tstamp' => $now,
            'crdate' => $now,
        ];

        // add all registered additional fields to field value and set default values
        foreach ($this->additionalFields as $fieldName) {
            if ($fieldName == 'orig_pid' || $fieldName == 'sortdate') {
                $fieldsValues[$fieldName] = 0;
            } else {
                $fieldsValues[$fieldName] = '';
            }
        }

        // merge filled additionalFields with ke_search fields
        if (count($additionalFields)) {
            $fieldsValues = array_merge($fieldsValues, $additionalFields);
        }

        return $fieldsValues;
    }

    /**
     * check if there are errors found in record before storing to db
     * @param int $storagePid
     * @param string $title
     * @param string $type
     * @param string $targetPid
     * @return bool
     */
    public function checkIfRecordHasErrorsBeforeIndexing($storagePid, $title, $type, $targetPid)
    {
        $errors = [];

        // check for empty values
        if (empty($storagePid)) {
            $this->logger->error('no storage pid set');
            $errors[] = 'No storage PID set';
        }
        if (empty($type)) {
            $this->logger->error('no type set');
            $errors[] = 'No type set';
        }
        if (empty($targetPid)) {
            $this->logger->error('No target PID set');
            $errors[] = 'No target PID set';
        }

        // collect error messages if an error was found
        if (count($errors)) {
            $errormessage = implode(',', $errors);
            if (!empty($type)) {
                $errormessage .= 'TYPE: ' . $type . '; ';
            }
            if (!empty($targetPid)) {
                $errormessage .= 'TARGET PID: ' . $targetPid . '; ';
            }
            if (!empty($storagePid)) {
                $errormessage .= 'STORAGE PID: ' . $storagePid . '; ';
            }
            $this->logger->error($errormessage);
            $this->indexingErrors[] = ' (' . $errormessage . ')';

            // break indexing and wait for next record to store
            return false;
        }
        return true;
    }

    /**
     * function getTag
     * @param int $tagUid
     * @param bool $clearText . If true returns the title of the tag. false return the tag itself
     * @return string
     */
    public function getTag($tagUid, $clearText = false)
    {
        $queryBuilder = Db::getQueryBuilder('tx_kesearch_index');

        $table = 'tx_kesearch_filteroptions';
        $where = $queryBuilder->expr()->eq(
            'uid',
            $queryBuilder->quote($tagUid, PDO::PARAM_INT)
        );

        $row = $queryBuilder
            ->select('title', 'tag')
            ->from($table)
            ->where($where)
            ->executeQuery()
            ->fetchAssociative();

        if ($clearText) {
            return $row['title'];
        }
        return $row['tag'];
    }

    /**
     * Strips control characters
     *
     * @param string $content content to sanitize
     * @return string
     * @see http://forge.typo3.org/issues/34808
     */
    public function stripControlCharacters($content)
    {
        // Printable utf-8 does not include any of these chars below x7F
        return preg_replace('@[\x00-\x08\x0B\x0C\x0E-\x1F]@', ' ', $content);
    }

    /**
     * this function returns all indexer configurations found in DB
     * independant of PID
     */
    public function getConfigurations()
    {
        $fields = '*';
        $table = 'tx_kesearch_indexerconfig';
        $queryBuilder = Db::getQueryBuilder('tx_kesearch_index');
        return $queryBuilder
            ->select($fields)
            ->from($table)
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * @param array $fieldValues
     * @return array
     */
    private function getDebugValuesFromFieldValues(array $fieldValues): array
    {
        unset($fieldValues['content']);
        return $fieldValues;
    }
}
