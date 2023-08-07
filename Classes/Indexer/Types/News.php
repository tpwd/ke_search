<?php

namespace Tpwd\KeSearch\Indexer\Types;

/***************************************************************
 *  Copyright notice
 *  (c) 2013 Christian Bülter
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
use Tpwd\KeSearch\Domain\Repository\CategoryRepository;
use Tpwd\KeSearch\Domain\Repository\IndexRepository;
use Tpwd\KeSearch\Domain\Repository\NewsRepository;
use Tpwd\KeSearch\Domain\Repository\PageRepository;
use Tpwd\KeSearch\Indexer\IndexerBase;
use Tpwd\KeSearch\Indexer\IndexerRunner;
use Tpwd\KeSearch\Lib\Db;
use Tpwd\KeSearch\Lib\SearchHelper;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;

/**
 * Plugin 'Faceted search' for the 'ke_search' extension.
 * @author    Andreas Kiefer
 * @author    Stefan Frömken
 * @author    Christian Bülter
 */
class News extends IndexerBase
{
    /**
     * Initializes indexer for news
     *
     * @param IndexerRunner $pObj
     */
    public function __construct($pObj)
    {
        parent::__construct($pObj);
        $this->pObj = $pObj;
    }

    /**
     * This function was called from indexer object and saves content to index table
     * @return string content which will be displayed in backend
     */
    public function startIndexing()
    {
        // get news Modul settings
        $newsSettings = $this->getNewsExtensionSettings();

        $content = '';
        $table = 'tx_news_domain_model_news';

        // get the pages from where to index the news
        $indexPids = $this->getPidList(
            $this->indexerConfig['startingpoints_recursive'],
            $this->indexerConfig['sysfolder'],
            $table
        );

        // add the tags of each page to the global page array
        if ($this->indexerConfig['index_use_page_tags']) {
            $this->pageRecords = $this->getPageRecords($indexPids);
            $this->addTagsToRecords($indexPids);
        }

        // get all the news entries to index, don't index hidden or
        // deleted news, BUT  get the news with frontend user group
        // access restrictions or time (start / stop) restrictions.
        // Copy those restrictions to the index.
        $fields = '*';
        $queryBuilder = Db::getQueryBuilder($table);
        $where = [];
        $where[] = $queryBuilder->expr()->in('pid', implode(',', $indexPids));

        // in incremental mode get only news which have been modified since last indexing time
        if ($this->indexingMode == self::INDEXING_MODE_INCREMENTAL) {
            $where[] = $queryBuilder->expr()->gte('tstamp', $this->lastRunStartTime);
        }

        // index only news records older than parameter timeRestriction parameter
        if (array_key_exists('timeRestriction', $newsSettings) && !empty($newsSettings['timeRestriction'])) {
            $timeRestriction = $newsSettings['timeRestriction'];
            $where[] = $queryBuilder->expr()->gt(
                'datetime',
                $queryBuilder->quote(strtotime($timeRestriction), \PDO::PARAM_INT)
            );
        }

        // index only news records that are younger than timeRestrictionHigh parameter
        if (array_key_exists('timeRestrictionHigh', $newsSettings) && !empty($newsSettings['timeRestrictionHigh'])) {
            $timeRestrictionHigh = $newsSettings['timeRestrictionHigh'];
            $where[] = $queryBuilder->expr()->lte(
                'datetime',
                $queryBuilder->quote(strtotime($timeRestrictionHigh), \PDO::PARAM_INT)
            );
        }

        // index archived news
        // 0: index all news
        // 1: index only active (not archived) news
        // 2: index only archived news
        if ($this->indexerConfig['index_news_archived'] == 1) {
            $where[] = $queryBuilder->expr()->orX(
                $queryBuilder->expr()->eq(
                    'archive',
                    $queryBuilder->quote(0, \PDO::PARAM_INT)
                ),
                $queryBuilder->expr()->gt(
                    'archive',
                    $queryBuilder->quote(time(), \PDO::PARAM_INT)
                )
            );
        } elseif ($this->indexerConfig['index_news_archived'] == 2) {
            $where[] = $queryBuilder->expr()->andX(
                $queryBuilder->expr()->gt(
                    'archive',
                    $queryBuilder->quote(0, \PDO::PARAM_INT)
                ),
                $queryBuilder->expr()->lt(
                    'archive',
                    $queryBuilder->quote(time(), \PDO::PARAM_INT)
                )
            );
        }

        $res = $queryBuilder
            ->select($fields)
            ->from($table)
            ->where(...$where)
            ->executeQuery();

        $indexedNewsCounter = 0;
        $resCount = $res->rowCount();

        if ($resCount) {
            while (($newsRecord = $res->fetchAssociative())) {
                $shouldBeIndexed = true;

                // get category data for this news record (list of
                // assigned categories and single view from category, if it exists)
                $categoryData = $this->getCategoryData($newsRecord);

                // If mode equals 2 ('choose categories for indexing')
                // check if the current news record has one of the categories
                // assigned that should be indexed.
                // mode 1 means 'index all news no matter what category
                // they have'
                if ($this->indexerConfig['index_news_category_mode'] == '2' && $this->indexerConfig['index_extnews_category_selection']) {
                    // load category configuration
                    $selectedCategoryUids = $this->getSelectedCategoriesUidList($this->indexerConfig['uid']);

                    $isInList = false;
                    foreach ($categoryData['uid_list'] as $catUid) {
                        // if category was found in list, set isInList to true and break further processing.
                        if (in_array($catUid, $selectedCategoryUids)) {
                            $isInList = true;
                            break;
                        }
                    }

                    // if category was not found stop further processing and continue with next news record
                    if (!$isInList) {
                        $shouldBeIndexed = false;
                    }
                }

                if (!$this->recordIsLive($newsRecord)) {
                    $shouldBeIndexed = false;
                }

                if ($shouldBeIndexed) {
                    $this->pObj->logger->debug('Indexing news record "' . $newsRecord['title'] . '"', [
                        'uid' => $newsRecord['uid'],
                        'pid' => $newsRecord['pid'],
                        'sys_language_uid' => $newsRecord['sys_language_uid'],
                    ]);
                } else {
                    $this->pObj->logger->debug('Skipping news record "' . $newsRecord['title'] . '"', [
                        'uid' => $newsRecord['uid'],
                        'pid' => $newsRecord['pid'],
                        'sys_language_uid' => $newsRecord['sys_language_uid'],
                    ]);
                    continue;
                }

                // compile the information which should go into the index:
                // title, teaser, bodytext
                $type = 'news';
                $title = strip_tags($newsRecord['title'] ?? '');
                $abstract = strip_tags($newsRecord['teaser'] ?? '');
                $content = strip_tags($newsRecord['bodytext'] ?? '');

                // add additional fields to the content:
                // alternative_title, author, author_email, keywords
                if (isset($newsRecord['author'])) {
                    $content .= "\n" . strip_tags($newsRecord['author']);
                }
                if (isset($newsRecord['author_email'])) {
                    $content .= "\n" . strip_tags($newsRecord['author_email']);
                }
                if (!empty($newsRecord['keywords'])) {
                    $content .= "\n" . strip_tags($newsRecord['keywords']);
                }

                // index attached content elements
                $contentElements = $this->getAttachedContentElements($newsRecord);
                $content .= $this->getContentFromContentElements($contentElements);

                // get related files if fileext is configured
                if (!empty($this->indexerConfig['fileext'])) {
                    $relatedFiles = $this->getRelatedFiles($newsRecord);
                    if (!empty($relatedFiles)) {
                        if ($this->indexerConfig['index_news_files_mode'] === 1) {
                            // add file content to news index record
                            $content .= $this->getContentFromRelatedFiles(
                                $relatedFiles,
                                $newsRecord['uid']
                            );
                        } else {
                            // index file as separate index record
                            $this->indexFilesAsSeparateResults($relatedFiles, $newsRecord);
                        }
                    }
                }

                // create content
                $fullContent = '';
                if (!empty($abstract)) {
                    $fullContent .= $abstract . "\n";
                }
                $fullContent .= $content;

                // make it possible to modify the indexerConfig via hook
                $indexerConfig = $this->indexerConfig;

                // create params and custom single view page:
                // if it is a default news (type = 0), add params
                // if it is an internal page (type = 1), put that into the "targetpid" field
                // if it is an external url (type = 2), put that into the "params" field
                if ($newsRecord['type'] == 1) {
                    $indexerConfig['targetpid'] = $newsRecord['internalurl'];
                    $params = '';
                } else {
                    if ($newsRecord['type'] == 2) {
                        $type = 'external:news';
                        $params = $newsRecord['externalurl'];
                    } else {
                        // overwrite the targetpid if there is a category assigned
                        // which has its own single view page
                        if ($categoryData['single_pid']) {
                            $indexerConfig['targetpid'] = $categoryData['single_pid'];
                        }

                        // create params for news single view, example:
                        // index.php?id=123&tx_news_pi1[news]=9&tx_news_pi1[controller]=News&tx_news_pi1[action]=detail
                        // NOTE that translated news records are linked by their l10n_parent uid (and overlaid later)
                        $paramsSingleView['tx_news_pi1']['news'] = $newsRecord['l10n_parent']
                            ? $newsRecord['l10n_parent']
                            : $newsRecord['uid'];
                        $paramsSingleView['tx_news_pi1']['controller'] = 'News';
                        $paramsSingleView['tx_news_pi1']['action'] = 'detail';
                        $params = '&' . http_build_query($paramsSingleView);
                        $params = rawurldecode($params);
                    }
                }

                // add tags from pages
                if ($indexerConfig['index_use_page_tags']) {
                    $tags = $this->pageRecords[(int)($newsRecord['pid'])]['tags'];
                } else {
                    $tags = '';
                }

                // add keywords from ext:news as tags
                $tags = $this->addTagsFromNewsKeywords($tags, $newsRecord);

                // add tags from ext:news as tags
                $tags = $this->addTagsFromNewsTags($tags, $newsRecord);

                // add categories from from ext:news as tags
                $tags = $this->addTagsFromNewsCategories($tags, $categoryData);

                // add system categories as tags
                SearchHelper::makeSystemCategoryTags($tags, $newsRecord['uid'], $table);

                // set additional fields
                $additionalFields = [];
                $additionalFields['orig_uid'] = $newsRecord['uid'];
                $additionalFields['orig_pid'] = $newsRecord['pid'];
                $additionalFields['sortdate'] = $newsRecord['crdate'];
                if (isset($newsRecord['datetime']) && $newsRecord['datetime'] > 0) {
                    $additionalFields['sortdate'] = $newsRecord['datetime'];
                }

                // hook for custom modifications of the indexed data, e.g. the tags
                if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['modifyExtNewsIndexEntry'] ?? null)) {
                    foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['modifyExtNewsIndexEntry'] as
                             $_classRef) {
                        $_procObj = GeneralUtility::makeInstance($_classRef);
                        $_procObj->modifyExtNewsIndexEntry(
                            $title,
                            $abstract,
                            $fullContent,
                            $params,
                            $tags,
                            $newsRecord,
                            $additionalFields,
                            $indexerConfig,
                            $categoryData,
                            $this
                        );
                    }
                }

                // store this record to the index
                $this->pObj->storeInIndex(
                    $indexerConfig['storagepid'],    // storage PID
                    $title,                          // page title
                    $type,                           // content type
                    $indexerConfig['targetpid'],     // target PID: where is the single view?
                    $fullContent,                    // indexed content, includes the title (linebreak after title)
                    $tags,                           // tags
                    $params,                         // typolink params for singleview
                    $abstract,                       // abstract
                    $newsRecord['sys_language_uid'], // language uid
                    $newsRecord['starttime'],        // starttime
                    $newsRecord['endtime'],          // endtime
                    $newsRecord['fe_group'],         // fe_group
                    false,                           // debug only?
                    $additionalFields                // additional fields added by hooks
                );
                $indexedNewsCounter++;
            }

            $logMessage = 'Indexer "' . $this->indexerConfig['title'] . '" finished'
                . ' (' . $indexedNewsCounter . ' records processed)';
            $this->pObj->logger->info($logMessage);
        } else {
            $this->pObj->logger->info('No news records found for indexing.');
        }
        return $indexedNewsCounter . ' News and ' . $this->fileCounter . ' related files have been indexed.';
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
     * Removes index records for the records which have been deleted since the last indexing.
     * Only needed in incremental indexing mode since there is a dedicated "cleanup" step in full indexing mode.
     *
     * @return string
     */
    public function removeDeleted(): string
    {
        /** @var IndexRepository $indexRepository */
        $indexRepository = GeneralUtility::makeInstance(IndexRepository::class);

        /** @var NewsRepository $newsRepository */
        $newsRepository = GeneralUtility::makeInstance(NewsRepository::class);

        // get the pages from where to index the news
        $folders = $this->getPagelist(
            $this->indexerConfig['startingpoints_recursive'],
            $this->indexerConfig['sysfolder']
        );

        // Fetch all records which have been deleted or hidden since the last indexing
        $records = $newsRepository->findAllDeletedAndHiddenByPidListAndTimestampInAllLanguages($folders, $this->lastRunStartTime);

        // and remove the corresponding index entries
        $count = $indexRepository->deleteCorrespondingIndexRecords('news', $records, $this->indexerConfig);
        $message = chr(10) . 'Found ' . $count . ' deleted or hidden record(s).';
        return $message;
    }

/**
 * checks if there is a category assigned to the $newsRecord which has
     * its own single view page and if yes, returns the uid of the page
     * in $catagoryData['single_pid'].
     * It also compiles a list of all assigned categories and returns
     * it as an array in $categoryData['uid_list']. The titles of the
     * categories are returned in $categoryData['title_list'] (array)
     *
     * @author Christian Bülter
     * @since 26.06.13 14:34
     * @param array $newsRecord
     * @return array
     */
    private function getCategoryData($newsRecord)
    {
        /** @var PageRepository $pageRepository */
        $pageRepository = GeneralUtility::makeInstance(PageRepository::class);
        /** @var CategoryRepository $categoryRepository */
        $categoryRepository = GeneralUtility::makeInstance(CategoryRepository::class);

        $categoryData = SearchHelper::getCategories($newsRecord['uid'], 'tx_news_domain_model_news');
        $categoryData['single_pid'] = 0;

        if ($categoryData['uid_list']) {
            foreach ($categoryData['uid_list']as $categoryUid) {
                $newsCat = $categoryRepository->findByUid($categoryUid);
                // check if this category has a single_pid and if this page really is reachable (not deleted, hidden or time restricted)
                if ($newsCat['single_pid'] && !$categoryData['single_pid'] && $pageRepository->findByUid($newsCat['single_pid'])) {
                    $categoryData['single_pid'] = $newsCat['single_pid'];
                }
            }
        }

        return $categoryData;
    }

    /**
     * adds tags from the ext:news "keywords" field to the index entry
     *
     * @author Christian Bülter
     * @since 26.06.13 14:27
     * @param string $tags
     * @param array $newsRecord
     * @return string
     */
    private function addTagsFromNewsKeywords($tags, $newsRecord)
    {
        if (!empty($newsRecord['keywords'])) {
            $keywordsList = GeneralUtility::trimExplode(',', $newsRecord['keywords']);
            foreach ($keywordsList as $keyword) {
                SearchHelper::makeTags($tags, [$keyword]);
            }
        }

        return $tags;
    }

    /**
     * Adds tags from the ext:news table "tags" as ke_search tags to the index entry
     *
     * @author Christian Bülter
     * @since 26.06.13 14:25
     * @param string $tags
     * @param array $newsRecord
     * @return string comma-separated list of tags
     */
    private function addTagsFromNewsTags($tags, $newsRecord)
    {
        $queryBuilder = Db::getQueryBuilder('tx_news_domain_model_news');
        $resTag = $queryBuilder
            ->select('tag.title')
            ->from('tx_news_domain_model_news', 'news')
            ->from('tx_news_domain_model_news_tag_mm', 'mm')
            ->from('tx_news_domain_model_tag', 'tag')
            ->where(
                $queryBuilder->expr()->eq(
                    'news.uid',
                    $queryBuilder->quoteIdentifier('mm.uid_local')
                ),
                $queryBuilder->expr()->eq(
                    'tag.uid',
                    $queryBuilder->quoteIdentifier('mm.uid_foreign')
                ),
                $queryBuilder->expr()->eq(
                    'news.uid',
                    $queryBuilder->createNamedParameter($newsRecord['uid'], \PDO::PARAM_INT)
                )
            )
            ->executeQuery();

        while (($newsTag = $resTag->fetchAssociative())) {
            SearchHelper::makeTags($tags, [$newsTag['title']]);
        }

        return $tags;
    }

    /**
     * creates tags from category titles
     *
     * @author Christian Bülter
     * @since 26.06.13 15:49
     * @param string $tags
     * @param array $categoryData
     * @return string
     */
    private function addTagsFromNewsCategories($tags, $categoryData)
    {
        SearchHelper::makeTags($tags, $categoryData['title_list']);
        return $tags;
    }

    /**
     * Fetches related content elements for a given news record.
     *
     * @author Christian Bülter
     * @since 15.10.15
     * @param array $newsRecord
     * @return array
     */
    public function getAttachedContentElements($newsRecord)
    {
        $queryBuilder = Db::getQueryBuilder('tt_content');
        $res = $queryBuilder
            ->select('*')
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->eq(
                    'tx_news_related_news',
                    $queryBuilder->createNamedParameter($newsRecord['uid'], \PDO::PARAM_INT)
                )
            )
            ->executeQuery();

        $contentElements = [];
        if ($res->rowCount()) {
            while (($contentElement = $res->fetchAssociative())) {
                $contentElements[] = $contentElement;
            }
        }

        return $contentElements;
    }

    /**
     * fetches the bare text content of an array of content elements.
     * makes use of the already given functions the page indexer provides.
     *
     * @author Christian Bülter
     * @since 15.10.15
     * @param array $contentElements
     * @return string
     */
    public function getContentFromContentElements($contentElements)
    {
        $content = '';

        // get content from content elements
        // NOTE: If the content elements contain links to files, those files will NOT be indexed.
        // NOTE: There's no restriction to certain content element types .
        // All attached content elements will be indexed. Only fields "header" and "bodytext" will be indexed.
        // todo: As in the page indexer it should be possible to define which fields should be indexed from the tt_content row.
        if (count($contentElements)) {
            /* @var $pageIndexerObject Page */
            $pageIndexerObject = GeneralUtility::makeInstance(Page::class, $this->pObj);

            foreach ($contentElements as $contentElement) {
                // index header, add header only if not set to "hidden"
                if ($contentElement['header_layout'] != 100) {
                    $content .= "\n" . strip_tags($contentElement['header']) . "\n";
                }

                // index bodytext (main content)
                $content .= "\n" . $pageIndexerObject->getContentFromContentElement($contentElement);
            }
        }

        return $content;
    }

    /**
     * get files related to current news record
     *
     * @param array $newsRecord
     * @return array list of files
     */
    protected function getRelatedFiles($newsRecord): array
    {
        return $this->getFilesToIndex(
            'tx_news_domain_model_news',
            'fal_related_files',
            $newsRecord['uid'],
            $newsRecord['sys_language_uid']
        );
    }

    /**
     * index related files as seperate file index records
     *
     * @param array $relatedFiles
     * @param array $newsRecord
     */
    protected function indexFilesAsSeparateResults($relatedFiles, $newsRecord)
    {
        parent::indexFilesAsSeparateResults($relatedFiles, $newsRecord);
    }

    /**
     * extract content from files to index
     *
     * @param array $relatedFiles
     * @param int $newsUid
     * @return string
     */
    protected function getContentFromRelatedFiles($relatedFiles, $newsUid): string
    {
        return $this->getContentFromFiles($relatedFiles);
    }

    /**
     * get news Extension typoscript Settings
     *
     * @return mixed
     * @throws \TYPO3\CMS\Extbase\Configuration\Exception\InvalidConfigurationTypeException
     */
    protected function getNewsExtensionSettings()
    {
        $configurationManager = GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\Configuration\ConfigurationManager::class);
        $typoscript = $configurationManager->getConfiguration(
            ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT,
            'news'
        );
        return $typoscript['plugin.']['tx_news.']['settings.'];
    }
}
