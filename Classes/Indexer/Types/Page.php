<?php

namespace Tpwd\KeSearch\Indexer\Types;

/* ***************************************************************
 *  Copyright notice
 *
 *  (c) 2010 Andreas Kiefer
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 * *************************************************************
 *
 * @author Andreas Kiefer
 * @author Christian Bülter
 */
use Tpwd\KeSearch\Domain\Repository\ContentRepository;
use Tpwd\KeSearch\Domain\Repository\IndexRepository;
use Tpwd\KeSearch\Domain\Repository\PageRepository;
use Tpwd\KeSearch\Indexer\IndexerBase;
use Tpwd\KeSearch\Indexer\IndexerRunner;
use Tpwd\KeSearch\Lib\Db;
use Tpwd\KeSearch\Lib\SearchHelper;
use Tpwd\KeSearch\Service\AdditionalContentService;
use Tpwd\KeSearch\Service\IndexerStatusService;
use Tpwd\KeSearch\Utility\ContentUtility;
use Tpwd\KeSearch\Utility\FileUtility;
use TYPO3\CMS\Backend\Configuration\TranslationConfigurationProvider;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Domain\Repository\PageRepository as CorePageRepository;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Resource\FileRepository;
use TYPO3\CMS\Core\Type\Bitmask\PageTranslationVisibility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\DataProcessing\FilesProcessor;

define('DONOTINDEX', -3);

/**
 * Plugin 'Faceted search' for the 'ke_search' extension.
 * @author    Andreas Kiefer
 * @author    Stefan Froemken
 * @author    Christian Bülter
 */
class Page extends IndexerBase
{
    /**
     * this array contains all data of all pages in the default language
     * @var array
     */
    public $pageRecords = [];

    /**
     * this array contains all data of all pages, but additionally with all available languages
     * @var array
     */
    public $cachedPageRecords = []; //

    /**
     * this array contains the system languages
     * @var array
     */
    public $sysLanguages = [];

    /**
     * this array contains the definition of which content element types should be indexed
     * @var array
     */
    public $defaultIndexCTypes = [
        'text',
        'textmedia',
        'textpic',
        'bullets',
        'table',
        'html',
        'header',
        'uploads',
        'shortcut',
    ];

    /**
     * this array contains the definition of which page
     * types (field doktype in pages table) should be indexed.
     * @var array
     * @see https://github.com/TYPO3/typo3/blob/10.4/typo3/sysext/core/Classes/Domain/Repository/PageRepository.php#L106
     */
    public $indexDokTypes = [CorePageRepository::DOKTYPE_DEFAULT];

    /*
     * Name of indexed elements. Will be overwritten in content element indexer.
     */
    public $indexedElementsName = 'pages';

    /* @var $fileRepository \TYPO3\CMS\Core\Resource\FileRepository */
    public $fileRepository;

    /**
     * @var ContentObjectRenderer
     */
    protected $cObj;

    /**
     * @var FilesProcessor
     */
    public $filesProcessor;

    /**
     * Files Processor configuration
     * @var array
     */
    public $filesProcessorConfiguration = [];

    /**
     * counter for how many pages we have indexed
     * @var int
     */
    public $counter = 0;

    /**
     * counter for how many pages without content we found
     * @var int
     */
    public $counterWithoutContent = 0;

    /**
     * counter for how many files we have indexed
     * @var int
     */
    public $fileCounter = 0;

    /**
     * sql query for content types
     * @var string
     */
    public $whereClauseForCType = '';

    /*
     * Service to process content from additional (related) tables
     */
    protected AdditionalContentService $additionalContentService;

    protected IndexerStatusService $indexerStatusService;

    /**
     * @param IndexerRunner $pObj
     */
    public function __construct($pObj)
    {
        parent::__construct($pObj);

        // set content types which should be indexed, fall back to default if not defined
        if (empty($this->indexerConfig['contenttypes'])) {
            $content_types_temp = $this->defaultIndexCTypes;
        } else {
            $content_types_temp = GeneralUtility::trimExplode(
                ',',
                $this->indexerConfig['contenttypes']
            );
        }

        // create a mysql WHERE clause for the content element types
        $cTypes = [];
        foreach ($content_types_temp as $value) {
            $cTypes[] = 'CType="' . $value . '"';
        }
        $this->whereClauseForCType = implode(' OR ', $cTypes);

        // Move DokTypes to class property
        if (!empty($this->indexerConfig['index_page_doctypes'])) {
            $this->indexDokTypes = GeneralUtility::trimExplode(
                ',',
                $this->indexerConfig['index_page_doctypes']
            );
        }

        // Create helper service for additional content
        $this->additionalContentService = $this->getAdditionalContentService();
        $this->additionalContentService->init($this->indexerConfig);

        // get all available sys_language_uid records
        /** @var TranslationConfigurationProvider $translationProvider */
        $translationProvider = GeneralUtility::makeInstance(TranslationConfigurationProvider::class);
        $startingPoints = [];
        $startingPoints += GeneralUtility::trimExplode(',', $this->indexerConfig['startingpoints_recursive'], true);
        $startingPoints += GeneralUtility::trimExplode(',', $this->indexerConfig['single_pages'], true);
        foreach ($startingPoints as $startingPoint) {
            foreach ($translationProvider->getSystemLanguages((int)$startingPoint) as $key => $lang) {
                $this->sysLanguages[$key] = $lang;
            }
        }

        // make file repository
        /* @var $this ->fileRepository \TYPO3\CMS\Core\Resource\FileRepository */
        $this->fileRepository = GeneralUtility::makeInstance(FileRepository::class);

        // make cObj
        $this->cObj = GeneralUtility::makeInstance(ContentObjectRenderer::class);

        // make filesProcessor
        $this->filesProcessor = GeneralUtility::makeInstance(FilesProcessor::class);

        $this->indexerStatusService = GeneralUtility::makeInstance(IndexerStatusService::class);
    }

    /**
     * This function was called from indexer object and saves content to index table
     * @return string content which will be displayed in backend
     */
    public function startIndexing()
    {
        // get all pages. Regardless if they are shortcut, sysfolder or external link
        $indexPids = $this->getPagelist(
            $this->indexerConfig['startingpoints_recursive'],
            $this->indexerConfig['single_pages']
        );

        // add complete page record to list of pids in $indexPids
        $this->pageRecords = $this->getPageRecords($indexPids);

        // create an array of cached page records which contains pages in
        // default and all other languages registered in the system
        foreach ($this->pageRecords as $pageRecord) {
            $this->addLocalizedPagesToCache($pageRecord);
        }

        // create a new list of allowed pids
        $indexPids = array_keys($this->pageRecords);

        // Remove unmodified pages in incremental mode
        if ($this->indexingMode == self::INDEXING_MODE_INCREMENTAL) {
            $this->removeUnmodifiedPageRecords($indexPids, $this->pageRecords, $this->cachedPageRecords);
        }

        // Stop if no pages for indexing have been found. Proceeding here would result in an error because we cannot
        // fetch an empty list of pages.
        if ($this->indexingMode == self::INDEXING_MODE_INCREMENTAL && empty($indexPids)) {
            $logMessage = 'No modified pages have been found, no indexing needed.';
            $this->pObj->logger->info($logMessage);
            return $logMessage;
        }

        // add tags to pages of doktype standard, advanced, shortcut and "not in menu"
        // add tags also to subpages of sysfolders (254), since we don't want them to be
        // excluded (see: http://forge.typo3.org/issues/49435)
        $where = ' (doktype = 1 OR doktype = 2 OR doktype = 4 OR doktype = 5 OR doktype = 254) ';

        // add the tags of each page to the global page array
        $this->addTagsToRecords($indexPids, $where);

        // loop through pids and collect page content and tags
        $counter = 0;
        foreach ($indexPids as $uid) {
            $this->indexerStatusService->setRunningStatus($this->indexerConfig, $counter, count($indexPids));
            $this->getPageContent($uid);
            $counter++;
        }

        $logMessage = 'Indexer "' . $this->indexerConfig['title'] . '" finished'
            . ' (' . count($indexPids) . ' records processed)';
        $this->pObj->logger->info($logMessage);

        // compile title of languages
        $languageTitles = '';
        foreach ($this->sysLanguages as $language) {
            if (strlen($languageTitles)) {
                $languageTitles .= ', ';
            }
            $languageTitles .= $language['title'];
        }

        // show indexer content
        $result =
            count($indexPids) . ' pages have been selected for indexing in the main language.' . chr(10)
            . count($this->sysLanguages) . ' languages (' . $languageTitles . ') have been found.' . chr(10)
            . $this->counter . ' ' . $this->indexedElementsName . ' have been indexed. ' . chr(10);

        if ($this->counterWithoutContent) {
            $result .= $this->counterWithoutContent . ' had no content or the content was not indexable.' . chr(10);
        }

        $result .= $this->fileCounter . ' files have been indexed.';

        return $result;
    }

    /**
     * @return string
     */
    public function startIncrementalIndexing(): string
    {
        $this->indexingMode = self::INDEXING_MODE_INCREMENTAL;
        $content = $this->startIndexing();
        $content .= $this->removeDeleted();
        return $content;
    }

    /**
     * Removes index records for the page records which have been deleted since the last indexing.
     * Only needed in incremental indexing mode since there is a dedicated "cleanup" step in full indexing mode.
     *
     * @return string
     */
    public function removeDeleted(): string
    {
        /** @var IndexRepository $indexRepository */
        $indexRepository = GeneralUtility::makeInstance(IndexRepository::class);

        /** @var PageRepository $pageRepository */
        $pageRepository = GeneralUtility::makeInstance(PageRepository::class);

        // get all pages (including deleted)
        $indexPids = $this->getPagelist(
            $this->indexerConfig['startingpoints_recursive'],
            $this->indexerConfig['single_pages'],
            true
        );

        // Fetch all pages which have been deleted since the last indexing
        $records = $pageRepository->findAllDeletedAndHiddenByUidListAndTimestampInAllLanguages($indexPids, $this->lastRunStartTime);

        // and remove the corresponding index entries
        $count = $indexRepository->deleteCorrespondingIndexRecords('page', $records, $this->indexerConfig);
        $message = chr(10) . 'Found ' . $count . ' deleted or hidden page(s).';

        return $message;
    }

    /**
     * get array with all pages
     * but remove all pages we don't want to have
     * @param array $uids Array with all page uids
     * @param string $whereClause Additional where clause for the query
     * @param string $table The table to select the fields from
     * @param string $fields The requested fields
     * @return array Array containing page records with all available fields
     */
    public function getPageRecords(array $uids, $whereClause = '', $table = 'pages', $fields = 'pages.*')
    {
        if (empty($uids)) {
            $this->pObj->logger->warning('No pages/sysfolders given.');
            return [];
        }

        $queryBuilder = Db::getQueryBuilder($table);
        $queryBuilder->getRestrictions()->removeAll();
        $pageQuery = $queryBuilder
            ->select('*')
            ->from($table)
            ->where(
                $queryBuilder->expr()->in(
                    'uid',
                    implode(',', $uids)
                )
            )
            ->executeQuery();

        $pageRows = [];
        while ($row = $pageQuery->fetchAssociative()) {
            $pageRows[$row['uid']] = $row;
        }

        return $pageRows;
    }

    /**
     * add localized page records to a cache/globalArray
     * This is much faster than requesting the DB for each tt_content-record
     * @param array $pageRow
     * @param bool $removeRestrictions
     */
    public function addLocalizedPagesToCache($pageRow, $removeRestrictions = false)
    {
        // create entry in cachedPageRecods for default language
        $this->cachedPageRecords[0][$pageRow['uid']] = $pageRow;

        // create entry in cachedPageRecods for additional languages, skip default language 0
        foreach ($this->sysLanguages as $sysLang) {
            if ($sysLang['uid'] != 0) {
                // get translations from "pages" not from "pages_language_overlay" if on TYPO3 9 or higher
                // see https://docs.typo3.org/typo3cms/extensions/core/Changelog/9.0/Breaking-82445-PagesAndPageTranslations.html
                $queryBuilder = Db::getQueryBuilder('pages');
                if ($removeRestrictions) {
                    $queryBuilder->getRestrictions()->removeAll();
                }
                $results = $queryBuilder
                    ->select('*')
                    ->from('pages')
                    ->where(
                        $queryBuilder->expr()->eq(
                            'l10n_parent',
                            $queryBuilder->quote($pageRow['uid'], \PDO::PARAM_INT)
                        ),
                        $queryBuilder->expr()->eq(
                            'sys_language_uid',
                            $queryBuilder->quote($sysLang['uid'], \PDO::PARAM_INT)
                        )
                    )
                    ->executeQuery()
                    ->fetchAllAssociative();

                $pageOverlay = $results[0] ?? false;
                if ($pageOverlay) {
                    $this->cachedPageRecords[$sysLang['uid']][$pageRow['uid']] = $pageOverlay + $pageRow;
                }
            }
        }
    }

    /**
     * Remove page records from $indexPids, $pageRecords and $cachedPageRecords which have not been modified since
     * last index run.
     *
     * @param array $indexPids
     * @param array $pageRecords
     * @param array $cachedPageRecords
     */
    public function removeUnmodifiedPageRecords(array & $indexPids, & $pageRecords = [], & $cachedPageRecords = [])
    {
        foreach ($indexPids as $uid) {
            $modified = false;

            // check page timestamp
            foreach ($this->sysLanguages as $sysLang) {
                if (
                    !empty($cachedPageRecords[$sysLang['uid']][$uid])
                    && $cachedPageRecords[$sysLang['uid']][$uid]['tstamp'] > $this->lastRunStartTime
                ) {
                    $modified = true;
                }
            }

            // check content elements timestamp
            /** @var ContentRepository $contentRepository */
            $contentRepository = GeneralUtility::makeInstance(ContentRepository::class);
            $newestContentElement = $contentRepository->findNewestByPid($uid, true);
            if (!empty($newestContentElement) && $newestContentElement['tstamp'] > $this->lastRunStartTime) {
                $modified = true;
            }

            // remove unmodified pages
            if (!$modified) {
                unset($pageRecords[$uid]);
                foreach ($this->sysLanguages as $sysLang) {
                    unset($cachedPageRecords[$sysLang['uid']][$uid]);
                }
                $key = array_search($uid, $indexPids);
                if ($key !== false) {
                    unset($indexPids[$key]);
                }
            }
        }
    }

    /**
     * creates a rootline and searches for valid access restrictions
     * returns the access restrictions for the given page as an array:
     *    $accessRestrictions = array(
     *        'hidden' => 0,
     *        'fe_group' => ',
     *        'starttime' => 0,
     *        'endtime' => 0
     *    );
     * @param int $currentPageUid
     * @return array
     */
    public function getInheritedAccessRestrictions($currentPageUid)
    {
        // get the rootline, start with the current page and go up
        $pageUid = $currentPageUid;
        $tempRootline = [(int)($this->cachedPageRecords[0][$currentPageUid]['pageUid'] ?? 0)];
        while (($this->cachedPageRecords[0][$pageUid]['pid'] ?? 0) > 0) {
            $pageUid = (int)($this->cachedPageRecords[0][$pageUid]['pid']);
            if (is_array($this->cachedPageRecords[0][$pageUid] ?? null)) {
                $tempRootline[] = $pageUid;
            }
        }

        // revert the ordering of the rootline so it starts with the
        // page at the top of the tree
        krsort($tempRootline);
        $rootline = [];
        foreach ($tempRootline as $pageUid) {
            $rootline[] = $pageUid;
        }

        // access restrictions:
        // a) hidden field
        // b) frontend groups
        // c) publishing and expiration date
        $inheritedAccessRestrictions = [
            'hidden' => 0,
            'fe_group' => '',
            'starttime' => 0,
            'endtime' => 0,
        ];

        // collect inherited access restrictions
        // since now we have a full rootline of the current page
        // (0 = level 0, 1 = level 1 and so on),
        // we can fetch the access restrictions from pages above
        foreach ($rootline as $pageUid) {
            if ($this->cachedPageRecords[0][$pageUid]['extendToSubpages'] ?? false) {
                $inheritedAccessRestrictions['hidden'] = $this->cachedPageRecords[0][$pageUid]['hidden'];
                $inheritedAccessRestrictions['fe_group'] = $this->cachedPageRecords[0][$pageUid]['fe_group'];
                $inheritedAccessRestrictions['starttime'] = $this->cachedPageRecords[0][$pageUid]['starttime'];
                $inheritedAccessRestrictions['endtime'] = $this->cachedPageRecords[0][$pageUid]['endtime'];
            }
        }

        // use access restrictions of current page if set otherwise use
        // inherited access restrictions
        $accessRestrictions = [
            'hidden' => $this->cachedPageRecords[0][$currentPageUid]['hidden']
                ? $this->cachedPageRecords[0][$currentPageUid]['hidden'] : $inheritedAccessRestrictions['hidden'],
            'fe_group' => $this->cachedPageRecords[0][$currentPageUid]['fe_group']
                ? $this->cachedPageRecords[0][$currentPageUid]['fe_group'] : $inheritedAccessRestrictions['fe_group'],
            'starttime' => $this->cachedPageRecords[0][$currentPageUid]['starttime']
                ? $this->cachedPageRecords[0][$currentPageUid]['starttime'] : $inheritedAccessRestrictions['starttime'],
            'endtime' => $this->cachedPageRecords[0][$currentPageUid]['endtime']
                ? $this->cachedPageRecords[0][$currentPageUid]['endtime'] : $inheritedAccessRestrictions['endtime'],
        ];

        return $accessRestrictions;
    }

    private function processShortcuts($rows, $fields, $depth = 99)
    {
        if (--$depth === 0) {
            return $rows;
        }
        $processedRows = [];
        foreach ($rows as $row) {
            if ($row['CType'] !== 'shortcut') {
                $processedRows[] = $row;
                continue;
            }

            $recordList = GeneralUtility::trimExplode(',', $row['records'], true);
            foreach ($recordList as $recordIdentifier) {
                $split = BackendUtility::splitTable_Uid($recordIdentifier);
                $tableName = empty($split[0]) ? 'tt_content' : $split[0];
                $uid = (int)($split[1] ?? 0);

                if ($tableName !== 'tt_content' || $uid === 0) {
                    continue;
                }

                $queryBuilder = Db::getQueryBuilder($tableName);
                $where = [];
                $where[] = $queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->createNamedParameter($uid, \PDO::PARAM_INT)
                );
                $where[] = $this->whereClauseForCType;

                $fieldArray = GeneralUtility::trimExplode(',', $fields);
                $referencedRow = $queryBuilder
                    ->select(...$fieldArray)
                    ->from($tableName)
                    ->where(...$where)
                    ->executeQuery()
                    ->fetchAssociative();

                if ($referencedRow) {
                    array_push($processedRows, ...$this->processShortcuts([$referencedRow], $fields, $depth));
                }
            }
        }
        return $processedRows;
    }

    /**
     * get content of current page and save data to db
     *
     * @param int $uid page-UID that has to be indexed
     */
    public function getPageContent($uid)
    {
        // define "fields" to fetch from tt_content and "content fields" which will be added to the index
        $contentFields = GeneralUtility::trimExplode(',', $this->indexerConfig['content_fields'] ?: 'bodytext');
        $fields =
            'uid,pid,header,CType,sys_language_uid,header_layout,fe_group,file_collections,filelink_sorting,records'
            . ',t3ver_state,t3ver_wsid'
            . ',' . implode(',', $contentFields);

        // If EXT:gridelements is installed, add the field containing the gridelement to the list
        if (ExtensionManagementUtility::isLoaded('gridelements')) {
            $fields .= ', tt_content.tx_gridelements_container';
        }

        // If EXT:container is installed, add the field containing the container id to the list
        if (ExtensionManagementUtility::isLoaded('container')) {
            $fields .= ', tt_content.tx_container_parent';
        }

        // hook to modify the page content fields
        if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['modifyPageContentFields'] ?? null)) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['modifyPageContentFields'] as $_classRef) {
                $_procObj = GeneralUtility::makeInstance($_classRef);
                $_procObj->modifyPageContentFields(
                    $fields,
                    $this
                );
            }
        }

        $table = 'tt_content';
        $queryBuilder = Db::getQueryBuilder($table);
        $where = [];
        $where[] = $queryBuilder->expr()->eq(
            'pid',
            $queryBuilder->createNamedParameter(
                $uid,
                \PDO::PARAM_INT
            )
        );
        $where[] = $this->whereClauseForCType;

        // Get access restrictions for this page, this access restrictions apply to all
        // content elements of this pages. Individual access restrictions
        // set for the content elements will be ignored. Use the content
        // element indexer if you need that feature!
        $pageAccessRestrictions = $this->getInheritedAccessRestrictions($uid);

        // add ke_search tags current page
        $tags = $this->pageRecords[(int)$uid]['tags'];

        // Compile content for this page from individual content elements with
        // respect to the language.
        // While doing so, fetch also content from attached files and write
        // their content directly to the index.
        $fieldArray = GeneralUtility::trimExplode(',', $fields);
        $ttContentRows = $queryBuilder
            ->select(...$fieldArray)
            ->from($table)
            ->where(...$where)
            ->executeQuery()
            ->fetchAllAssociative();

        $pageContent = [];
        if (count($ttContentRows)) {
            $ttContentRows = $this->processShortcuts($ttContentRows, $fields);
            foreach ($ttContentRows as $ttContentRow) {
                // Skip content elements inside hidden containers and for other (custom) reasons
                if (!$this->contentElementShouldBeIndexed($ttContentRow)) {
                    continue;
                }

                $content = '';
                $fileObjects = [];

                // index header
                // add header only if not set to "hidden", do not add header of html element
                if ($ttContentRow['header_layout'] != 100 && $ttContentRow['CType'] != 'html') {
                    $content .= strip_tags($ttContentRow['header']) . "\n";
                }

                // index content of this content element and find attached or linked files.
                // Attached files are saved as file references, the RTE links directly to
                // a file, thus we get file objects.
                // Files go into the index no matter if "index_content_with_restrictions" is set
                // or not, that means even if protected content elements do not go into the index,
                // files do. Since each file gets its own index entry with correct access
                // restrictions, that's no problem from an access permission perspective (in fact, it's a feature).
                foreach ($contentFields as $field) {
                    $fileObjects = array_merge(
                        $fileObjects,
                        $this->findAttachedFiles($ttContentRow),
                        $this->additionalContentService->findLinkedFiles($ttContentRow, $field)
                    );
                    $content .= $this->getContentFromContentElement($ttContentRow, $field) . "\n";
                }
                $additionalContentAndFiles = $this->additionalContentService->getContentAndFilesFromAdditionalTables($ttContentRow);
                $content .= $additionalContentAndFiles['content'] . "\n";
                $fileObjects = array_merge($fileObjects, $additionalContentAndFiles['files']);

                // index the files found
                if (!$pageAccessRestrictions['hidden']
                    && $this->checkIfpageShouldBeIndexed($uid, $this->pageRecords[(int)$uid]['sys_language_uid'])
                    && !empty($fileObjects)
                ) {
                    $this->indexFiles($fileObjects, $ttContentRow, $pageAccessRestrictions['fe_group'], $tags);
                }

                // add content from this content element to page content
                // ONLY if this content element is not access protected
                // or protected content elements should go into the index
                // by configuration.
                if ($this->indexerConfig['index_content_with_restrictions'] == 'yes'
                    || $ttContentRow['fe_group'] == ''
                    || $ttContentRow['fe_group'] == '0'
                ) {
                    if (!isset($pageContent[$ttContentRow['sys_language_uid']])) {
                        $pageContent[$ttContentRow['sys_language_uid']] = '';
                    }
                    $pageContent[$ttContentRow['sys_language_uid']] .= $content;

                    // add content elements with sys_language_uid = -1 to all language versions of this page
                    if ($ttContentRow['sys_language_uid'] == -1) {
                        foreach ($this->sysLanguages as $sysLang) {
                            if ($sysLang['uid'] != -1) {
                                if (!isset($pageContent[$sysLang['uid']])) {
                                    $pageContent[$sysLang['uid']] = '';
                                }
                                $pageContent[$sysLang['uid']] .= $content;
                            }
                        }
                    }
                }
            }
        } else {
            $this->counterWithoutContent++;
        }

        // make it possible to modify the indexerConfig via hook
        $additionalFields = [];
        $indexerConfig = $this->indexerConfig;

        // make it possible to modify the default values via hook
        $indexEntryDefaultValues = [
            'type' => 'page',
            'uid' => $uid,
            'params' => '',
            'feGroupsPages' => $pageAccessRestrictions['fe_group'],
            'debugOnly' => false,
        ];

        // hook for custom modifications of the indexed data, e. g. the tags
        if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['modifyPagesIndexEntry'] ?? null)) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['modifyPagesIndexEntry'] as $_classRef) {
                $_procObj = GeneralUtility::makeInstance($_classRef);
                $_procObj->modifyPagesIndexEntry(
                    $uid,
                    $pageContent,
                    $tags,
                    $this->cachedPageRecords,
                    $additionalFields,
                    $indexerConfig,
                    $indexEntryDefaultValues,
                    $this
                );
            }
        }

        // store record in index table
        if (count($pageContent)) {
            foreach ($pageContent as $language_uid => $content) {
                $pageTitle = $this->cachedPageRecords[$language_uid][$uid]['title'] ?? '[empty title]';
                if (!$pageAccessRestrictions['hidden'] && $this->checkIfpageShouldBeIndexed($uid, $language_uid)) {
                    $this->pObj->logger->debug('Indexing page "' . $pageTitle . '" (UID ' . $uid . ', L ' . $language_uid . ')');
                    // overwrite access restrictions with language overlay values
                    $accessRestrictionsLanguageOverlay = $pageAccessRestrictions;
                    $pageAccessRestrictions['fe_group'] = $indexEntryDefaultValues['feGroupsPages'];
                    if ($language_uid > 0) {
                        if ($this->cachedPageRecords[$language_uid][$uid]['fe_group']) {
                            $accessRestrictionsLanguageOverlay['fe_group'] =
                                $this->cachedPageRecords[$language_uid][$uid]['fe_group'];
                        }
                        if ($this->cachedPageRecords[$language_uid][$uid]['starttime']) {
                            $accessRestrictionsLanguageOverlay['starttime'] =
                                $this->cachedPageRecords[$language_uid][$uid]['starttime'];
                        }
                        if ($this->cachedPageRecords[$language_uid][$uid]['endtime']) {
                            $accessRestrictionsLanguageOverlay['endtime'] =
                                $this->cachedPageRecords[$language_uid][$uid]['endtime'];
                        }
                    }

                    // use tx_kesearch_abstract instead of "abstract" if set
                    $abstract = (string)($this->cachedPageRecords[$language_uid][$uid]['tx_kesearch_abstract']
                        ?: $this->cachedPageRecords[$language_uid][$uid]['abstract']);

                    $this->pObj->storeInIndex(
                        $indexerConfig['storagepid'],                               // storage PID
                        $this->cachedPageRecords[$language_uid][$uid]['title'],     // page title
                        $indexEntryDefaultValues['type'],                           // content type
                        (string)$indexEntryDefaultValues['uid'],                    // target PID / single view
                        $content,                        // indexed content, includes the title (linebreak after title)
                        $tags,                                                      // tags
                        $indexEntryDefaultValues['params'],                         // typolink params for singleview
                        $abstract,                                                  // abstract
                        $language_uid,                                              // language uid
                        $accessRestrictionsLanguageOverlay['starttime'],            // starttime
                        $accessRestrictionsLanguageOverlay['endtime'],              // endtime
                        $accessRestrictionsLanguageOverlay['fe_group'],             // fe_group
                        $indexEntryDefaultValues['debugOnly'],                      // debug only?
                        $additionalFields                                           // additional fields added by hooks
                    );
                    $this->counter++;
                } else {
                    $this->pObj->logger->debug('Skipping page ' . $pageTitle . ' (UID ' . $uid . ', L ' . $language_uid . ')');
                }
            }
        }
    }

    /**
     * Checks if the given row from tt_content should really be indexed by checking if the content element
     * sits inside a container (EXT:gridelements, EXT:container) and if this container is visible.
     *
     * @param $ttContentRow
     * @return bool
     */
    public function contentElementShouldBeIndexed($ttContentRow)
    {
        $contentElementShouldBeIndexed = true;

        // If gridelements is installed, check if the content element sits inside a gridelements container.
        // If yes, check if the container is hidden or placed outside the page (colPos: -2).
        // This adds a query for each content element which may result in slow indexing. But simply
        // joining the tt_content table to itself does not work either, since then all content elements which
        // are not located inside a gridelement won't be indexed then.
        if (ExtensionManagementUtility::isLoaded('gridelements') && $ttContentRow['tx_gridelements_container']) {
            $queryBuilder = Db::getQueryBuilder('tt_content');
            $gridelementsContainer = $queryBuilder
                ->select(...['colPos', 'hidden'])
                ->from('tt_content')
                ->where(
                    $queryBuilder->expr()->eq(
                        'uid',
                        $queryBuilder->createNamedParameter($ttContentRow['tx_gridelements_container'])
                    )
                )
                ->executeQuery()
                ->fetchAssociative();

            // If there's no gridelement container found, it means it is hidden or deleted or time restricted.
            // In this case, skip the content element.
            if ($gridelementsContainer === false) {
                $contentElementShouldBeIndexed = false;
            } else {
                // If the colPos of the gridelement container is -2, it is not on the page, so skip it.
                if ($gridelementsContainer['colPos'] === -2) {
                    $contentElementShouldBeIndexed = false;
                }
            }
        }

        // If EXT:container is installed, check if the content element sits inside a container element
        if (ExtensionManagementUtility::isLoaded('container') && $ttContentRow['tx_container_parent']) {
            $queryBuilder = Db::getQueryBuilder('tt_content');
            $container = $queryBuilder
                ->select('uid')
                ->from('tt_content')
                ->where(
                    $queryBuilder->expr()->eq(
                        'uid',
                        $queryBuilder->createNamedParameter($ttContentRow['tx_container_parent'])
                    )
                )
                ->executeQuery()
                ->fetchAssociative();

            // If there's no container found, it means it is hidden or deleted or time restricted.
            // In this case, skip the content element.
            $contentElementShouldBeIndexed = !($container === false);
        }

        if (!$this->recordIsLive($ttContentRow)) {
            $contentElementShouldBeIndexed = false;
        }

        // hook to add custom check if this content element should be indexed
        if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['contentElementShouldBeIndexed'] ?? null)) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['contentElementShouldBeIndexed'] as $_classRef) {
                $_procObj = GeneralUtility::makeInstance($_classRef);
                $contentElementShouldBeIndexed = $_procObj->contentElementShouldBeIndexed(
                    $ttContentRow,
                    $contentElementShouldBeIndexed,
                    $this
                );
            }
        }

        return $contentElementShouldBeIndexed;
    }

    /**
     * Checks if the given page should go to the index.
     * Checks the doktype and flags like "hidden", "no_index" and versioning.
     *
     * are set.
     *
     * @param int $uid
     * @param int $language_uid
     * @return bool
     */
    public function checkIfpageShouldBeIndexed($uid, $language_uid)
    {
        $index = true;

        if ($this->cachedPageRecords[$language_uid][$uid]['hidden'] ?? false) {
            $index = false;
        }

        if ($this->cachedPageRecords[$language_uid][$uid]['no_search'] ?? false) {
            $index = false;
        }

        if (!in_array($this->cachedPageRecords[$language_uid][$uid]['doktype'] ?? 0, $this->indexDokTypes)) {
            $index = false;
        }

        $pageTranslationVisibility = new PageTranslationVisibility((int)($this->cachedPageRecords[$language_uid][$uid]['l18n_cfg'] ?? 0));
        if ((int)$language_uid === 0 && $pageTranslationVisibility->shouldBeHiddenInDefaultLanguage()) {
            $index = false;
        }

        if (!empty($this->cachedPageRecords[$language_uid][$uid]) && !$this->recordIsLive($this->cachedPageRecords[$language_uid][$uid])) {
            $index = false;
        }

        return $index;
    }

    /**
     * combine group access restrictons from page(s) and content element
     * @param string $feGroupsPages comma list
     * @param string $feGroupsContentElement comma list
     * @return string
     * @author Christian Bülter
     * @since 26.09.13
     */
    public function getCombinedFeGroupsForContentElement($feGroupsPages, $feGroupsContentElement)
    {
        // combine frontend groups from page(s) and content element as follows
        // 1. if page has no groups, but ce has groups, use ce groups
        // 2. if ce has no groups, but page has groups, use page groups
        // 3. if page has "show at any login" (-2) and ce has groups, use ce groups
        // 4. if ce has "show at any login" (-2) and page has groups, use page groups
        // 5. if page and ce have explicit groups (not "hide at login" (-1), merge them (use only groups both have)
        // 6. if page or ce has "hide at login" and the other
        // has an explicit group the element will never be shown and we must not index it.
        // So which group do we set here? Let's use a constant for that and check in the calling function for that.

        $feGroups = '';

        if (!$feGroupsPages && $feGroupsContentElement) {
            $feGroups = $feGroupsContentElement;
        }

        if ($feGroupsPages && !$feGroupsContentElement) {
            $feGroups = $feGroupsPages;
        }

        if ($feGroupsPages == '-2' && $feGroupsContentElement) {
            $feGroups = $feGroupsContentElement;
        }

        if ($feGroupsPages && $feGroupsContentElement == '-2') {
            $feGroups = $feGroupsPages;
        }

        if ($feGroupsPages && $feGroupsContentElement && $feGroupsPages != '-1' && $feGroupsContentElement != '-1') {
            $feGroupsContentElementArray = GeneralUtility::intExplode(
                ',',
                $feGroupsContentElement
            );
            $feGroupsPagesArray = GeneralUtility::intExplode(',', $feGroupsPages);
            $feGroups = implode(',', array_intersect($feGroupsContentElementArray, $feGroupsPagesArray));
        }

        if (($feGroupsContentElement
                && $feGroupsContentElement != '-1'
                && $feGroupsContentElement != -2
                && $feGroupsPages == '-1')
            ||
            ($feGroupsPages && $feGroupsPages != '-1' && $feGroupsPages != -2 && $feGroupsContentElement == '-1')
        ) {
            $feGroups = DONOTINDEX;
        }

        return $feGroups;
    }

    /**
     * Extracts content from files given (as array of file objects or file reference objects)
     * and writes the content to the index
     * @param array $fileObjects
     * @param array $ttContentRow
     * @param string $feGroupsPages comma list
     * @param string $tags string
     * @author Christian Bülter
     * @since 25.09.13
     */
    public function indexFiles($fileObjects, $ttContentRow, $feGroupsPages, $tags)
    {
        // combine group access restrictions from page(s) and content element
        $feGroups = $this->getCombinedFeGroupsForContentElement($feGroupsPages, $ttContentRow['fe_group']);

        if (count($fileObjects) && $feGroups != DONOTINDEX) {
            foreach ($fileObjects as $fileObject) {
                $isIndexable = false;
                if ($fileObject instanceof FileInterface) {
                    $file = ($fileObject instanceof FileReference) ? $fileObject->getOriginalFile() : $fileObject;
                    $isHiddenFileReference = ($fileObject instanceof FileReference) && $fileObject->getProperty('hidden');
                    $isIndexable = $file instanceof \TYPO3\CMS\Core\Resource\File
                        && FileUtility::isFileIndexable($file, $this->indexerConfig)
                        && !$isHiddenFileReference;
                } else {
                    $errorMessage = 'Could not index file in content element #' . $ttContentRow['uid'] . ' (no file object).';
                    $this->pObj->logger->warning($errorMessage);
                    $this->addError($errorMessage);
                }

                if ($isIndexable) {
                    // get file path and URI
                    $filePath = $fileObject->getForLocalProcessing(false);

                    /* @var $fileIndexerObject File */
                    $fileIndexerObject = GeneralUtility::makeInstance(File::class, $this->pObj);

                    // add tags from linking page to this index record?
                    if (!$this->indexerConfig['index_use_page_tags_for_files']) {
                        $tags = '';
                    }

                    // add tag to identify this index record as file
                    SearchHelper::makeTags($tags, ['file']);

                    // get file information and  file content (using external tools)
                    // write file data to the index as a seperate index entry
                    // count indexed files, add it to the indexer output
                    if (!file_exists($filePath)) {
                        $errorMessage = 'Could not index file ' . $filePath . ' in content element #' . $ttContentRow['uid'] . ' (file does not exist).';
                        $this->pObj->logger->warning($errorMessage);
                        $this->addError($errorMessage);
                    } else {
                        if ($fileIndexerObject->fileInfo->setFile($fileObject)) {
                            if (($content = $fileIndexerObject->getFileContent($filePath))) {
                                $this->storeFileContentToIndex(
                                    $fileObject,
                                    $content,
                                    $fileIndexerObject,
                                    $feGroups,
                                    $tags,
                                    $ttContentRow
                                );
                                $this->fileCounter++;
                            } else {
                                $this->addError($fileIndexerObject->getErrors());
                                $errorMessage = 'Could not index file ' . $filePath . '.';
                                $this->pObj->logger->warning($errorMessage);
                                $this->addError($errorMessage);
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Finds files attached to the content elements
     * returns them as file reference objects array
     * @author Christian Bülter
     * @since 24.09.13
     * @param array $ttContentRow content element
     * @return array
     */
    public function findAttachedFiles($ttContentRow)
    {
        // Set current data
        $this->cObj->data = $ttContentRow;

        // Get files by filesProcessor
        $processedData = [];

        // set tt_content fields used for file references
        if (empty($this->indexerConfig['file_reference_fields'])) {
            $filesProcessorConfiguration = $this->setFilesProcessorConfiguration(['media']);
        } else {
            $fileReferenceFields = GeneralUtility::trimExplode(
                ',',
                $this->indexerConfig['file_reference_fields']
            );
            $filesProcessorConfiguration = $this->setFilesProcessorConfiguration($fileReferenceFields);
        }

        $fileReferenceObjects = [];
        foreach ($filesProcessorConfiguration as $configuration) {
            $processedData = $this->filesProcessor->process($this->cObj, [], $configuration, $processedData);
            $fileReferenceObjects = array_merge($fileReferenceObjects, $processedData['files']);
        }

        return $fileReferenceObjects;
    }

    /**
     * Store the file content and additional information to the index
     * $fileObject is either a file reference object or file object
     *
     * @param $fileObject
     * @param string $content file text content
     * @param File $fileIndexerObject
     * @param string $feGroups comma list of groups to assign
     * @param $tags
     * @param array $ttContentRow tt_content element the file was assigned to
     * @author Christian Bülter
     * @since 25.09.13
     */
    public function storeFileContentToIndex($fileObject, $content, $fileIndexerObject, $feGroups, $tags, $ttContentRow)
    {
        // get metadata
        if ($fileObject instanceof FileReference) {
            $orig_uid = $fileObject->getOriginalFile()->getUid();
            $metadata = $fileObject->getOriginalFile()->getMetaData()->get();
        } else {
            $orig_uid = $fileObject->getUid();
            $metadata = $fileObject->getMetaData()->get();
        }

        if (empty($metadata)) {
            if ($fileObject instanceof FileReference) {
                $errorMessage = sprintf(
                    'Could not get meta data from file reference [uid:%d], file [uid:%d] (%s).',
                    $fileObject->getUid(),
                    $fileObject->getOriginalFile()->getUid(),
                    $fileObject->getOriginalFile()->getIdentifier()
                );
            } else {
                $errorMessage = sprintf(
                    'Could not get meta data from file [uid:%d] (%s).',
                    $fileObject->getUid(),
                    $fileObject->getIdentifier()
                );
            }

            $this->pObj->logger->error($errorMessage);
            $this->addError($errorMessage);

            return;
        }

        if (isset($metadata['fe_groups']) && !empty($metadata['fe_groups'])) {
            if ($feGroups) {
                $feGroupsContentArray = GeneralUtility::intExplode(',', $feGroups);
                $feGroupsFileArray = GeneralUtility::intExplode(',', $metadata['fe_groups']);
                $feGroups = implode(',', array_intersect($feGroupsContentArray, $feGroupsFileArray));
            } else {
                $feGroups = $metadata['fe_groups'];
            }
        }

        // assign categories as tags (as cleartext, eg. "colorblue")
        $categories = SearchHelper::getCategories($metadata['uid'], 'sys_file_metadata');
        SearchHelper::makeTags($tags, $categories['title_list']);

        // assign categories as generic tags (eg. "syscat123")
        SearchHelper::makeSystemCategoryTags($tags, $metadata['uid'], 'sys_file_metadata');

        if ($metadata['title']) {
            $content = $metadata['title'] . "\n" . $content;
        }

        $abstract = '';
        if ($metadata['description'] ?? null) {
            $abstract = $metadata['description'];
            $content = $metadata['description'] . "\n" . $content;
        }

        if ($metadata['alternative'] ?? null) {
            $content .= "\n" . $metadata['alternative'];
        }

        $title = $fileIndexerObject->fileInfo->getName();
        $storagePid = $this->indexerConfig['storagepid'];
        $type = 'file:' . $fileObject->getExtension();

        $additionalFields = [
            'sortdate' => $fileIndexerObject->fileInfo->getModificationTime(),
            'orig_uid' => $orig_uid,
            'orig_pid' => 0,
            'directory' => $fileIndexerObject->fileInfo->getAbsolutePath(),
            'hash' => $fileIndexerObject->getUniqueHashForFile(),
        ];

        //hook for custom modifications of the indexed data, e. g. the tags
        if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['modifyFileIndexEntryFromContentIndexer'] ?? null)) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['modifyFileIndexEntryFromContentIndexer'] as
                     $_classRef) {
                $_procObj = GeneralUtility::makeInstance($_classRef);
                $_procObj->modifyFileIndexEntryFromContentIndexer(
                    $fileObject,
                    $content,
                    $fileIndexerObject,
                    $feGroups,
                    $ttContentRow,
                    $storagePid,
                    $title,
                    $tags,
                    $abstract,
                    $additionalFields
                );
            }
        }

        // Store record in index table:
        // Add usergroup restrictions of the page and the
        // content element to the index data.
        // Add time restrictions to the index data.
        $this->pObj->storeInIndex(
            $storagePid,                             // storage PID
            $title,                                  // file name
            $type,                                   // content type
            $ttContentRow['pid'],                    // target PID: where is the single view?
            $content,                                // indexed content
            $tags,                                   // tags
            '',                                      // typolink params for singleview
            $abstract,                               // abstract
            $ttContentRow['sys_language_uid'],       // language uid
            $ttContentRow['starttime'] ?? 0,              // starttime
            $ttContentRow['endtime'] ?? 0,                // endtime
            $feGroups,                               // fe_group
            false,                                   // debug only?
            $additionalFields                        // additional fields added by hooks
        );
    }

    /**
     * Extracts one field of content from the given content element (either from table tt_content or from
     * additional table) and returns it as plain text
     *
     * @param array $ttContentRow content element
     * @param string $field field from which the plain text content should be fetched
     * @return string
     * @since 24.09.13
     * @author Christian Bülter
     */
    public function getContentFromContentElement(array $ttContentRow, string $field = 'bodytext'): string
    {
        $content = ContentUtility::getPlainContentFromContentRow(
            $ttContentRow,
            $field,
            $GLOBALS['TCA']['tt_content']['columns'][$field]['config']['type'] ?? ''
        );

        // hook for modifiying a content elements content
        if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['modifyContentFromContentElement'] ?? null)) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['modifyContentFromContentElement'] as
                     $_classRef) {
                $_procObj = GeneralUtility::makeInstance($_classRef);
                $_procObj->modifyContentFromContentElement(
                    $content,
                    $ttContentRow,
                    $this,
                    $field
                );
            }
        }

        return $content;
    }

    /**
     * Set the $filesProcessorConfiguration according to the Page-Indexer-Configuration
     *
     * @param array $fileReferenceFields
     * @return array
     */
    protected function setFilesProcessorConfiguration(array $fileReferenceFields): array
    {
        $filesProcessorConfiguration = [];
        foreach ($fileReferenceFields as $fileReferenceField) {
            $filesProcessorConfiguration[] = [
                'references.' => [
                    'fieldName' => $fileReferenceField,
                    'table' => 'tt_content',
                ],
                'collections.' => [
                    'field' => 'file_collections',
                ],
                'sorting.' => [
                    'field ' => 'filelink_sorting',
                ],
                'as' => 'files',
            ];
        }
        return $filesProcessorConfiguration;
    }

    public function setContentObjectRenderer(ContentObjectRenderer $cObj): void
    {
        $this->cObj = $cObj;
    }

    protected function getAdditionalContentService(): AdditionalContentService
    {
        return GeneralUtility::makeInstance(AdditionalContentService::class);
    }
}
