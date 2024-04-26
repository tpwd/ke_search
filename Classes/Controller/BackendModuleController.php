<?php

/***************************************************************
 *  Copyright notice
 *  (c) 2010 Andreas Kiefer
 *  (c) 2016 Bernhard Berger <bernhard.berger@gmail.com>
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

namespace Tpwd\KeSearch\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Tpwd\KeSearch\Domain\Repository\IndexRepository;
use Tpwd\KeSearch\Indexer\IndexerBase;
use Tpwd\KeSearch\Indexer\IndexerRunner;
use Tpwd\KeSearch\Lib\Db;
use Tpwd\KeSearch\Lib\SearchHelper;
use Tpwd\KeSearch\Pagination\SlidingWindowPagination as BackportedSlidingWindowPagination;
use Tpwd\KeSearch\Service\IndexerStatusService;
use TYPO3\CMS\Backend\Module\ModuleData;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Pagination\ArrayPaginator;
use TYPO3\CMS\Core\Pagination\SlidingWindowPagination;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Class BackendModuleController
 */
class BackendModuleController
{
    protected ModuleTemplateFactory $moduleTemplateFactory;
    protected IndexRepository $indexRepository;
    protected ModuleTemplate $moduleTemplate;
    protected int $pageId = 0;
    protected ?string $do;
    protected PageRenderer $pageRenderer;
    protected IndexerStatusService $indexerStatusService;

    public function __construct(
        IndexRepository $indexRepository,
        ModuleTemplateFactory $moduleTemplateFactory,
        PageRenderer $pageRenderer,
        IndexerStatusService $indexerStatusService
    ) {
        $this->indexRepository = $indexRepository;
        $this->moduleTemplateFactory = $moduleTemplateFactory;
        $this->pageRenderer = $pageRenderer;
        $this->indexerStatusService = $indexerStatusService;
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $GLOBALS['LANG']->includeLLFile('LLL:EXT:ke_search/Resources/Private/Language/locallang_mod.xlf');

        $moduleTemplate = $this->moduleTemplateFactory->create($request);
        $this->pageId = (int)($request->getQueryParams()['id'] ?? 0);
        $this->do = $request->getQueryParams()['do'] ?? null;
        $backendUser = $this->getBackendUser();
        $function = 'function1';

        if (GeneralUtility::makeInstance(Typo3Version::class)->getMajorVersion() > 11) {
            /** @var ModuleData $moduleData */
            $moduleData = $request->getAttribute('moduleData');
            $function = $moduleData->get('function', 'function1');
        } else {
            $moduleData = $backendUser->getModuleData('web_KeSearchBackendModule');
            if ($moduleData['function'] ?? '') {
                $function = $moduleData['function'];
            }
        }

        if ($this->do) {
            switch ($this->do) {
                case 'function1':
                case 'function2':
                case 'function3':
                case 'function4':
                case 'function5':
                case 'function6':
                    $function = $this->do;
                    break;
                case 'clear':
                    $function = 'function5';
                    break;
            }
            if (GeneralUtility::makeInstance(Typo3Version::class)->getMajorVersion() > 11) {
                $moduleData->set('function', $function);
                $backendUser->pushModuleData($moduleData->getModuleIdentifier(), $moduleData->toArray());
            } else {
                $moduleData = [
                    'function' => $function,
                ];
                $backendUser->pushModuleData('web_KeSearchBackendModule', $moduleData);
            }
        }

        switch ($function) {
            case 'function2':
                return $this->indexedContentAction($request, $moduleTemplate);
            case 'function3':
                return $this->indexTableInformationAction($request, $moduleTemplate);
            case 'function4':
                return $this->searchwordStatisticsAction($request, $moduleTemplate);
            case 'function5':
                return $this->clearSearchIndexAction($request, $moduleTemplate);
            case 'function6':
                return $this->lastIndexingReportAction($request, $moduleTemplate);
            default:
                return $this->startIndexingAction($request, $moduleTemplate);
        }
    }

    /**
     * start indexing action
     */
    public function startIndexingAction(ServerRequestInterface $request, ModuleTemplate $moduleTemplate): ResponseInterface
    {
        /* @var $indexer IndexerRunner */
        $indexer = GeneralUtility::makeInstance(IndexerRunner::class);
        $indexerConfigurations = $indexer->getConfigurations();
        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);

        if (GeneralUtility::makeInstance(Typo3Version::class)->getMajorVersion() < 12) {
            $this->pageRenderer->addJsFile('EXT:ke_search/Resources/Public/JavaScript/v11/getIndexerStatusRequest.js');
        } else {
            // @phpstan-ignore-next-line
            $this->pageRenderer->loadJavaScriptModule('@tpwd/ke-search/getIndexerStatusRequest.js');
        }

        $indexingMode = (int)($request->getQueryParams()['indexingMode'] ?? IndexerBase::INDEXING_MODE_FULL);
        if (!in_array($indexingMode, [IndexerBase::INDEXING_MODE_INCREMENTAL, IndexerBase::INDEXING_MODE_FULL])) {
            $indexingMode = IndexerBase::INDEXING_MODE_FULL;
        }

        $content = '';

        // action: start indexer or remove lock
        if ($this->do == 'startindexer') {
            // start indexing in verbose mode with cleanup process
            $content .= $indexer->startIndexing(true, [], '', $indexingMode);
        } else {
            if ($this->do == 'rmLock') {
                // remove lock from registry - admin only!
                if ($this->getBackendUser()->isAdmin()) {
                    $this->indexerStatusService->clearAll();
                } else {
                    $content .=
                        '<p>'
                        . LocalizationUtility::translate(
                            'LLL:EXT:ke_search/Resources/Private/Language/locallang_mod.xlf:not_allowed_remove_indexer_lock',
                            'KeSearch'
                        )
                        . '</p>';
                }
            }
        }

        // check for index process lock in registry
        // remove lock if older than 12 hours
        $lockTime = $this->indexerStatusService->getIndexerStartTime();
        $compareTime = time() - (60 * 60 * 12);
        if ($lockTime !== 0 && $lockTime < $compareTime) {
            // lock is older than 12 hours
            // remove lock and show "start index" button
            $this->indexerStatusService->clearAll();
            $lockTime = 0;
        }

        // show information about indexer configurations and number of records
        // if action "start indexing" is not selected
        if ($this->do != 'startindexer') {
            $content .= '<div id="kesearch-indexer-overview">';
            $content .= $this->printNumberOfRecords();
            $content .= $this->printIndexerConfigurations($indexerConfigurations);
            $content .= '</div>';
        }

        // show "start indexing" or "remove lock" button
        if ($lockTime !== 0) {
            if (!$this->getBackendUser()->isAdmin()) {
                // print warning message for non-admins
                $content .= '<div class="row"><div class="col-md-8">';
                $content .= '<div id="kesearch-indexer-running-warning" class="alert alert-danger">';
                $content .= '<p>WARNING!</p>';
                $content .= 'The indexer is already running and can not be started twice.';
                $content .= '</div>';
                $content .= '</div></div>';
            } else {
                // show 'remove lock' button for admins
                $content .= '<div class="row"><div class="col-md-8">';
                $content .= '<div id="kesearch-indexer-running-warning" class="alert alert-info">';
                $content .= 'The indexer is already running and can not be started twice.';
                $content .= '</div>';
                $content .= '<p>The indexing process was started at <strong>' . SearchHelper::formatTimestamp($lockTime) . '.</p></strong>';
                $content .= '<p>You can remove the lock by clicking the following button.</p>';
                $content .= '</div></div>';
                $moduleUrl = $uriBuilder->buildUriFromRoute(
                    'web_KeSearchBackendModule',
                    [
                        'id' => $this->pageId,
                        'do' => 'rmLock',
                    ]
                );
                $content .= '<a class="btn btn-danger" id="kesearch-button-removelock" href="' . $moduleUrl . '">Remove Lock</a> ';
            }
        } else {
            // no lock set - show "start indexer" link if indexer configurations have been found
            if ($indexerConfigurations) {
                $moduleUrl = $uriBuilder->buildUriFromRoute(
                    'web_KeSearchBackendModule',
                    [
                        'id' => $this->pageId,
                        'do' => 'startindexer',
                    ]
                );
                $content .= '<a class="btn btn-info" id="kesearch-button-start-full" href="' . $moduleUrl . '">'
                    . LocalizationUtility::translate('backend.start_indexer_full', 'ke_search')
                    . '</a>';
                $moduleUrl = $uriBuilder->buildUriFromRoute(
                    'web_KeSearchBackendModule',
                    [
                        'id' => $this->pageId,
                        'do' => 'startindexer',
                        'indexingMode' => IndexerBase::INDEXING_MODE_INCREMENTAL,
                    ]
                );
                $content .= ' <a class="btn btn-info" id="kesearch-button-start-incremental" href="' . $moduleUrl . '">'
                    . LocalizationUtility::translate('backend.start_indexer_incremental', 'ke_search')
                    . '</a>';
            } else {
                $content .=
                    '<div class="alert alert-info">'
                    .
                    LocalizationUtility::translate(
                        'LLL:EXT:ke_search/Resources/Private/Language/locallang_mod.xlf:no_indexer_configurations',
                        'KeSearch'
                    )
                    . '</div>';
            }
        }

        $moduleUrl = $uriBuilder->buildUriFromRoute('web_KeSearchBackendModule', ['id' => $this->pageId]);
        $content .= ' <a class="btn btn-default" id="kesearch-button-reload" href="' . $moduleUrl . '">Reload</a>';

        $this->addMainMenu($request, $moduleTemplate, 'startIndexing');

        if (GeneralUtility::makeInstance(Typo3Version::class)->getMajorVersion() < 12) {
            $moduleTemplate->getView()->setTemplateRootPaths(['EXT:ke_search/Resources/Private/Templates/BackendModule']);
            $moduleTemplate->getView()->setLayoutRootPaths(['EXT:ke_search/Resources/Private/Layouts/']);
            $moduleTemplate->getView()->setTemplatePathAndFilename('EXT:ke_search/Resources/Private/Templates/BackendModule/StartIndexing.html');
            $moduleTemplate->getView()->assign('content', $content);
            // @extensionScannerIgnoreLine
            return new HtmlResponse($moduleTemplate->renderContent());
        }
        $moduleTemplate->assign('content', $content);
        return $moduleTemplate->renderResponse('BackendModule/StartIndexing');
    }

    /**
     * indexed content action
     */
    public function indexedContentAction(ServerRequestInterface $request, ModuleTemplate $moduleTemplate): ResponseInterface
    {
        if ($this->pageId) {
            $perms_clause = $this->getBackendUser()->getPagePermsClause(1);
            $pageInfo = BackendUtility::readPageAccess($this->pageId, $perms_clause);
            $pagePath = GeneralUtility::fixed_lgd_cs($pageInfo['_thePath'], -200);

            $indexRecords = $this->indexRepository->findByPageUidToShowIndexedContent($this->pageId);
            $currentPage = (int)($request->getQueryParams()['currentPage'] ?? 1);
            $paginator = new ArrayPaginator($indexRecords, $currentPage, 20);
            if (GeneralUtility::makeInstance(Typo3Version::class)->getMajorVersion() < 12) {
                $pagination = new BackportedSlidingWindowPagination($paginator, 15);
            } else {
                // PHPStan is complaining that the SlidingWindowPagination class does not exist in TYPO3 11,
                // so we ignore this error for now
                // Todo: Remove the PHPStan annotation below once support for TYPO3 11 is dropped
                // @phpstan-ignore-next-line
                $pagination = new SlidingWindowPagination($paginator, 15);
            }
        }

        $this->addMainMenu($request, $moduleTemplate, 'indexedContent');

        if (GeneralUtility::makeInstance(Typo3Version::class)->getMajorVersion() < 12) {
            $moduleTemplate->getView()->setTemplateRootPaths(['EXT:ke_search/Resources/Private/Templates/BackendModule']);
            $moduleTemplate->getView()->setLayoutRootPaths(['EXT:ke_search/Resources/Private/Layouts/']);
            $moduleTemplate->getView()->setPartialRootPaths(
                array_merge(
                    $moduleTemplate->getView()->getPartialRootPaths(),
                    ['EXT:ke_search/Resources/Private/Partials/']
                )
            );
            $moduleTemplate->getView()->setTemplatePathAndFilename('EXT:ke_search/Resources/Private/Templates/BackendModule/IndexedContent.html');
            $moduleTemplate->getView()->assign('pagination', $pagination ?? null);
            $moduleTemplate->getView()->assign('paginator', $paginator ?? null);
            $moduleTemplate->getView()->assign('do', $this->do ?? '');
            $moduleTemplate->getView()->assign('pageId', $this->pageId ?? 0);
            $moduleTemplate->getView()->assign('currentPage', $currentPage ?? 1);
            $moduleTemplate->getView()->assign('pagePath', $pagePath ?? '');
            // @extensionScannerIgnoreLine
            return new HtmlResponse($moduleTemplate->renderContent());
        }
        $moduleTemplate->assign('pagination', $pagination ?? null);
        $moduleTemplate->assign('paginator', $paginator ?? null);
        $moduleTemplate->assign('do', $this->do ?? '');
        $moduleTemplate->assign('pageId', $this->pageId ?? 0);
        $moduleTemplate->assign('currentPage', $currentPage ?? 1);
        $moduleTemplate->assign('pagePath', $pagePath ?? '');

        return $moduleTemplate->renderResponse('BackendModule/IndexedContent');
    }

    /**
     * index table information action
     */
    public function indexTableInformationAction(ServerRequestInterface $request, ModuleTemplate $moduleTemplate): ResponseInterface
    {
        $content = $this->renderIndexTableInformation();

        $this->addMainMenu($request, $moduleTemplate, 'indexTableInformation');

        if (GeneralUtility::makeInstance(Typo3Version::class)->getMajorVersion() < 12) {
            $moduleTemplate->getView()->setTemplateRootPaths(['EXT:ke_search/Resources/Private/Templates/BackendModule']);
            $moduleTemplate->getView()->setLayoutRootPaths(['EXT:ke_search/Resources/Private/Layouts/']);
            $moduleTemplate->getView()->setTemplatePathAndFilename('EXT:ke_search/Resources/Private/Templates/BackendModule/IndexTableInformation.html');
            $moduleTemplate->getView()->assign('content', $content);
            // @extensionScannerIgnoreLine
            return new HtmlResponse($moduleTemplate->renderContent());
        }
        $moduleTemplate->assign('content', $content);
        return $moduleTemplate->renderResponse('BackendModule/IndexTableInformation');
    }

    /**
     * searchword statistics action
     */
    public function searchwordStatisticsAction(ServerRequestInterface $request, ModuleTemplate $moduleTemplate): ResponseInterface
    {
        // days to show
        $days = 30;
        $data = $this->getSearchwordStatistics($this->pageId, $days);

        $error = null;
        if ($data['error'] ?? false) {
            $error = $data['error'];
            unset($data['error']);
        }

        $this->addMainMenu($request, $moduleTemplate, 'searchwordStatistics');

        if (GeneralUtility::makeInstance(Typo3Version::class)->getMajorVersion() < 12) {
            $moduleTemplate->getView()->setTemplateRootPaths(['EXT:ke_search/Resources/Private/Templates/BackendModule']);
            $moduleTemplate->getView()->setLayoutRootPaths(['EXT:ke_search/Resources/Private/Layouts/']);
            $moduleTemplate->getView()->setTemplatePathAndFilename('EXT:ke_search/Resources/Private/Templates/BackendModule/SearchwordStatistics.html');
            $moduleTemplate->getView()->assign('days', $days);
            $moduleTemplate->getView()->assign('data', $data);
            $moduleTemplate->getView()->assign('error', $error);
            $moduleTemplate->getView()->assign('languages', $this->getLanguages());
            // @extensionScannerIgnoreLine
            return new HtmlResponse($moduleTemplate->renderContent());
        }
        $moduleTemplate->assign('days', $days);
        $moduleTemplate->assign('data', $data);
        $moduleTemplate->assign('error', $error);
        $moduleTemplate->assign('languages', $this->getLanguages());
        return $moduleTemplate->renderResponse('BackendModule/SearchwordStatistics');
    }

    /**
     * clear search index action
     */
    public function clearSearchIndexAction(ServerRequestInterface $request, ModuleTemplate $moduleTemplate): ResponseInterface
    {
        // get uri builder
        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);

        // admin only access
        if ($this->getBackendUser()->isAdmin()) {
            if ($this->do == 'clear') {
                $databaseConnection = Db::getDatabaseConnection('tx_kesearch_index');
                $databaseConnection->truncate('tx_kesearch_index');
            }
        }

        // build "clear index" link
        $moduleUrl = $uriBuilder->buildUriFromRoute(
            'web_KeSearchBackendModule',
            [
                'id' => $this->pageId,
                'do' => 'clear',
            ]
        );

        $this->addMainMenu($request, $moduleTemplate, 'clearSearchIndex');

        if (GeneralUtility::makeInstance(Typo3Version::class)->getMajorVersion() < 12) {
            $moduleTemplate->getView()->setTemplateRootPaths(['EXT:ke_search/Resources/Private/Templates/BackendModule']);
            $moduleTemplate->getView()->setLayoutRootPaths(['EXT:ke_search/Resources/Private/Layouts/']);
            $moduleTemplate->getView()->setTemplatePathAndFilename('EXT:ke_search/Resources/Private/Templates/BackendModule/ClearSearchIndex.html');
            $moduleTemplate->getView()->assign('moduleUrl', $moduleUrl);
            $moduleTemplate->getView()->assign('isAdmin', $this->getBackendUser()->isAdmin());
            $moduleTemplate->getView()->assign('indexCount', $this->indexRepository->getTotalNumberOfRecords());
            // @extensionScannerIgnoreLine
            return new HtmlResponse($moduleTemplate->renderContent());
        }
        $moduleTemplate->assign('moduleUrl', $moduleUrl);
        $moduleTemplate->assign('isAdmin', $this->getBackendUser()->isAdmin());
        $moduleTemplate->assign('indexCount', $this->indexRepository->getTotalNumberOfRecords());
        return $moduleTemplate->renderResponse('BackendModule/ClearSearchIndex');
    }

    /**
     * last indexing report action
     */
    public function lastIndexingReportAction(ServerRequestInterface $request, ModuleTemplate $moduleTemplate): ResponseInterface
    {
        $this->addMainMenu($request, $moduleTemplate, 'lastIndexingReport');

        if (GeneralUtility::makeInstance(Typo3Version::class)->getMajorVersion() < 12) {
            $moduleTemplate->getView()->setTemplateRootPaths(['EXT:ke_search/Resources/Private/Templates/BackendModule']);
            $moduleTemplate->getView()->setLayoutRootPaths(['EXT:ke_search/Resources/Private/Layouts/']);
            $moduleTemplate->getView()->setTemplatePathAndFilename('EXT:ke_search/Resources/Private/Templates/BackendModule/LastIndexingReport.html');
            $moduleTemplate->getView()->assign('logEntry', $this->getLastIndexingReport());
            // @extensionScannerIgnoreLine
            return new HtmlResponse($moduleTemplate->renderContent());
        }
        $moduleTemplate->assign('logEntry', $this->getLastIndexingReport());
        return $moduleTemplate->renderResponse('BackendModule/LastIndexingReport');
    }

    /**
     * get report from sys_log
     * @author Christian Bülter
     * @since 29.05.15
     */
    public function getLastIndexingReport()
    {
        $queryBuilder = Db::getQueryBuilder('sys_log');
        $logResults = $queryBuilder
            ->select('*')
            ->from('sys_log')
            ->where(
                $queryBuilder->expr()->like(
                    'details',
                    $queryBuilder->quote('[ke_search]%', \PDO::PARAM_STR)
                )
            )
            ->orderBy('tstamp', 'DESC')
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAllAssociative();

        return $logResults;
    }

    /**
     * prints the indexer configurations available
     * @param array $indexerConfigurations
     * @author Christian Bülter
     * @since 28.04.15
     * @return string
     */
    public function printIndexerConfigurations($indexerConfigurations)
    {
        $content = '<div id="kesearch-startindexing-indexers">';
        if ($indexerConfigurations) {
            $content .= '<div class="row"><div class="col-md-8">';
            $content .= '<div class="table-fit"><table class="table table-striped table-hover">';
            $content .= '<colgroup><col><col width="100"><col width="100"><col width="100"></colgroup>';
            $content .= '<tr><th>Indexer configuration</th><th>Type</th><th>UID</th><th>PID</th></tr>';
            foreach ($indexerConfigurations as $indexerConfiguration) {
                $content .= '<tr>'
                    . '<td>' . $this->encode($indexerConfiguration['title']) . '</td>'
                    . '<td>' . $indexerConfiguration['type'] . '</td>'
                    . '<td>' . $indexerConfiguration['uid'] . '</td>'
                    . '<td>' . $indexerConfiguration['pid'] . '</td>'
                    . '</tr>';
            }
            $content .= '</table></div>';
            $content .= '</div></div></div>';
        }

        return $content;
    }

    /**
     * prints number of records in index
     * @author Christian Bülter
     * @since 28.04.15
     */
    public function printNumberOfRecords()
    {
        $content = '<div id="kesearch-startindexing-statistics">';
        $numberOfRecords = $this->indexRepository->getTotalNumberOfRecords();

        if ($numberOfRecords) {
            $content .= '<div class="row"><div class="col-md-8">';
            $content .= '<div class="alert alert-info">';
            $content .= LocalizationUtility::translate(
                'LLL:EXT:ke_search/Resources/Private/Language/locallang_mod.xlf:index_contains',
                'KeSearch'
            )
                . ' <strong>' . $numberOfRecords . '</strong> '
                . LocalizationUtility::translate(
                    'LLL:EXT:ke_search/Resources/Private/Language/locallang_mod.xlf:records',
                    'KeSearch'
                ) . '.<br />' . chr(10);

            $lastRun = $this->indexerStatusService->getLastRunTime();
            if ($lastRun) {
                $content .= LocalizationUtility::translate(
                    'LLL:EXT:ke_search/Resources/Private/Language/locallang_mod.xlf:last_indexing',
                    'KeSearch'
                )
                    . ' ' . SearchHelper::formatTimestamp($lastRun['endTime']);
            }
            $content .= '</div>';
            $content .= '</div></div>';

            $content .= '<div class="row"><div class="col-md-8">';
            $content .= '<div class="table-fit"><table class="table table-striped table-hover">';
            $content .= '<colgroup><col><col width="100"></colgroup>';
            $content .= '<tr><th>Type of indexed content</th><th>Count</th></tr>';

            /** @var IndexRepository $indexRepository */
            $indexRepository = GeneralUtility::makeInstance(IndexRepository::class);
            $results_per_type = $indexRepository->getNumberOfRecordsInIndexPerType();
            foreach ($results_per_type as $type => $count) {
                $content .= '<tr><td>' . $type . '</td><td>' . $count . '</td></tr>';
            }

            $content .= '</table></div>';
            $content .= '</div></div></div>';
        }

        return $content;
    }

    /*
     * function renderIndexTableInformation
     */
    public function renderIndexTableInformation()
    {
        $table = 'tx_kesearch_index';

        // get table status
        $databaseConnection = Db::getDatabaseConnection($table);
        $tableStatusQuery = 'SHOW TABLE STATUS';
        $tableStatusRows = $databaseConnection->fetchAllAssociative($tableStatusQuery);
        $content = '';

        foreach ($tableStatusRows as $row) {
            if ($row['Name'] == $table) {
                $dataLength = $this->formatFilesize($row['Data_length']);
                $indexLength = $this->formatFilesize($row['Index_length']);
                $completeLength = $this->formatFilesize($row['Data_length'] + $row['Index_length']);

                $content .= '
                <div class="row"><div class="col-md-4"><div class="table-fit">
                        <table class="table table-striped table-hover">
                            <colgroup><col><col width="100"></colgroup>
                            <tr>
                                <td>Records: </td>
                                <td>' . $row['Rows'] . '</td>
                            </tr>
                            <tr>
                                <td>Data size: </td>
                                <td>' . $dataLength . '</td>
                            </tr>
                            <tr>
                                <td>Index size: </td>
                                <td>' . $indexLength . '</td>
                            </tr>
                            <tr>
                                <td>Complete table size: </td>
                                <td>' . $completeLength . '</td>
                            </tr>
                        </table>
              </div></div></div>';
            }
        }

        /** @var IndexRepository $indexRepository */
        $indexRepository = GeneralUtility::makeInstance(IndexRepository::class);
        $results_per_type = $indexRepository->getNumberOfRecordsInIndexPerType();
        if (count($results_per_type)) {
            $content .= '<div class="row"><div class="col-md-4"><div class="table-fit">';
            $content .= '<table class="table table-striped table-hover">';
            $content .= '<colgroup><col><col width="100"></colgroup>';
            foreach ($results_per_type as $type => $count) {
                $content .= '<tr><td><span class="label label-primary">' . $type . '</span></td><td>' . $count . '</td></tr>';
            }
            $content .= '</table>';
            $content .= '</div></div></div>';
        }

        return $content;
    }

    /**
     * format file size from bytes to human readable format
     */
    public function formatFilesize($size, $decimals = 0)
    {
        $sizes = [' B', ' KB', ' MB', ' GB', ' TB', ' PB', ' EB', ' ZB', ' YB'];
        if ($size == 0) {
            return 'n/a';
        }
        return round($size / pow(1024, ($i = floor(log($size, 1024)))), $decimals) . $sizes[$i];
    }

    /**
     * @param string $input
     * @return string
     */
    public function encode($input)
    {
        return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    }

    /**
     * @param int $pageUid
     * @param int $days
     * @return array
     */
    public function getSearchwordStatistics($pageUid, $days)
    {
        $statisticData = [];

        if (!$pageUid) {
            $statisticData['error'] = LocalizationUtility::translate(
                'LLL:EXT:ke_search/Resources/Private/Language/locallang_mod.xlf:select_a_page',
                'KeSearch'
            );
            return $statisticData;
        }

        // calculate statistic start
        $timestampStart = time() - ($days * 60 * 60 * 24);

        // get data from sysfolder or from single page?
        $isSysFolder = $this->checkSysfolder();

        // set folder or single page where the data is selected from
        $pidWhere = $isSysFolder ? ' AND pid=' . (int)$pageUid . ' ' : ' AND pageid=' . (int)$pageUid . ' ';

        // get languages
        $queryBuilder = Db::getQueryBuilder('tx_kesearch_stat_word');
        $queryBuilder->getRestrictions()->removeAll();
        $languageResult = $queryBuilder
            ->select('language')
            ->from('tx_kesearch_stat_word')
            ->where(
                $queryBuilder->expr()->gt(
                    'tstamp',
                    $queryBuilder->quote($timestampStart, \PDO::PARAM_INT)
                ),
                $queryBuilder->expr()->eq(
                    $isSysFolder ? 'pid' : 'pageid',
                    $queryBuilder->quote($pageUid, \PDO::PARAM_INT)
                )
            )
            ->groupBy('language')
            ->executeQuery()
            ->fetchAllAssociative();

        $content = '';
        if (!count($languageResult)) {
            $statisticData['error'] =
                'No statistic data found! Please select the sysfolder
                where your index is stored or the page where your search plugin is placed.';
            return $statisticData;
        }

        foreach ($languageResult as $languageRow) {
            if ($isSysFolder) {
                $statisticData[$languageRow['language']]['searchphrase'] = $this->getStatisticTableData(
                    'tx_kesearch_stat_search',
                    $languageRow['language'],
                    $timestampStart,
                    $pidWhere,
                    'searchphrase'
                );
            } else {
                $statisticData['error'] = 'Please select the sysfolder where your index is stored for a list of search phrases';
            }

            $statisticData[$languageRow['language']]['word'] = $this->getStatisticTableData(
                'tx_kesearch_stat_word',
                $languageRow['language'],
                $timestampStart,
                $pidWhere,
                'word'
            );
        }

        return $statisticData;
    }

    /**
     * @param string $table
     * @param int $language
     * @param int $timestampStart
     * @param string $pidWhere
     * @param string $tableCol
     */
    public function getStatisticTableData($table, $language, $timestampStart, $pidWhere, $tableCol)
    {
        // get statistic data from db
        $queryBuilder = Db::getQueryBuilder($table);
        $queryBuilder->getRestrictions()->removeAll();
        $statisticData = $queryBuilder
            ->add('select', 'count(' . $tableCol . ') as num, ' . $tableCol)
            ->from($table)
            ->add(
                'where',
                'tstamp > ' . $queryBuilder->quote($timestampStart, \PDO::PARAM_INT) .
                ' AND language=' . $queryBuilder->quote($language, \PDO::PARAM_INT) . ' ' .
                $pidWhere
            )
            ->add('groupBy', $tableCol . ' HAVING count(' . $tableCol . ')>0')
            ->add('orderBy', 'num desc')
            ->executeQuery()
            ->fetchAllAssociative();

        return $statisticData;
    }

    /*
     * check if selected page is a sysfolder
     *
     * @return boolean
     */
    public function checkSysfolder()
    {
        $queryBuilder = Db::getQueryBuilder('pages');
        $page = $queryBuilder
            ->select('doktype')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->quote($this->pageId, \PDO::PARAM_INT)
                )
            )
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        return $page['doktype'] == 254 ? true : false;
    }

    /**
     * Returns the Backend User
     * @return BackendUserAuthentication
     */
    protected function getBackendUser()
    {
        return $GLOBALS['BE_USER'];
    }

    /**
     * @return array
     */
    protected function getLanguages()
    {
        $languages = [];

        $queryBuilder = Db::getQueryBuilder('sys_language');
        $languageRows = $queryBuilder
            ->select('language')
            ->from('tx_kesearch_index')
            ->groupBy('language')
            ->executeQuery()
            ->fetchAllAssociative();

        foreach ($languageRows as $row) {
            $languages[$row['language']] = $row['language'];
        }

        return $languages;
    }

    protected function addMainMenu(ServerRequestInterface $request, ModuleTemplate $view, string $currentAction): void
    {
        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
        $menu = $view->getDocHeaderComponent()->getMenuRegistry()->makeMenu();
        $menu->setIdentifier('KeSearchModuleMenu');

        $menu->addMenuItem(
            $menu->makeMenuItem()
                ->setTitle(LocalizationUtility::translate('LLL:EXT:ke_search/Resources/Private/Language/locallang_mod.xlf:function1', 'ke_search'))
                ->setHref(
                    $uriBuilder->buildUriFromRoute(
                        'web_KeSearchBackendModule',
                        [
                            'id' => $this->pageId,
                            'do' => 'function1',
                        ]
                    )
                )
                ->setActive($currentAction === 'startIndexing')
        );
        $menu->addMenuItem(
            $menu->makeMenuItem()
                ->setTitle(LocalizationUtility::translate('LLL:EXT:ke_search/Resources/Private/Language/locallang_mod.xlf:function2', 'ke_search'))
                ->setHref(
                    $uriBuilder->buildUriFromRoute(
                        'web_KeSearchBackendModule',
                        [
                            'id' => $this->pageId,
                            'do' => 'function2',
                        ]
                    )
                )
                ->setActive($currentAction === 'indexedContent')
        );
        $menu->addMenuItem(
            $menu->makeMenuItem()
                ->setTitle(LocalizationUtility::translate('LLL:EXT:ke_search/Resources/Private/Language/locallang_mod.xlf:function3', 'ke_search'))
                ->setHref(
                    $uriBuilder->buildUriFromRoute(
                        'web_KeSearchBackendModule',
                        [
                            'id' => $this->pageId,
                            'do' => 'function3',
                        ]
                    )
                )
                ->setActive($currentAction === 'indexTableInformation')
        );
        $menu->addMenuItem(
            $menu->makeMenuItem()
                ->setTitle(LocalizationUtility::translate('LLL:EXT:ke_search/Resources/Private/Language/locallang_mod.xlf:function4', 'ke_search'))
                ->setHref(
                    $uriBuilder->buildUriFromRoute(
                        'web_KeSearchBackendModule',
                        [
                            'id' => $this->pageId,
                            'do' => 'function4',
                        ]
                    )
                )
                ->setActive($currentAction === 'searchwordStatistics')
        );
        $menu->addMenuItem(
            $menu->makeMenuItem()
                ->setTitle(LocalizationUtility::translate('LLL:EXT:ke_search/Resources/Private/Language/locallang_mod.xlf:function5', 'ke_search'))
                ->setHref(
                    $uriBuilder->buildUriFromRoute(
                        'web_KeSearchBackendModule',
                        [
                            'id' => $this->pageId,
                            'do' => 'function5',
                        ]
                    )
                )
                ->setActive($currentAction === 'clearSearchIndex')
        );
        $menu->addMenuItem(
            $menu->makeMenuItem()
                ->setTitle(LocalizationUtility::translate('LLL:EXT:ke_search/Resources/Private/Language/locallang_mod.xlf:function6', 'ke_search'))
                ->setHref(
                    $uriBuilder->buildUriFromRoute(
                        'web_KeSearchBackendModule',
                        [
                            'id' => $this->pageId,
                            'do' => 'function6',
                        ]
                    )
                )
                ->setActive($currentAction === 'lastIndexingReport')
        );
        $view->getDocHeaderComponent()->getMenuRegistry()->addMenu($menu);
    }
}
