<?php

namespace Tpwd\KeSearch\Indexer\Types;

use Tpwd\KeSearch\Domain\Repository\IndexRepository;
use Tpwd\KeSearch\Domain\Repository\TtContentRepository;
use Tpwd\KeSearch\Lib\Db;
use Tpwd\KeSearch\Lib\SearchHelper;
use TYPO3\CMS\Core\Database\Query\Restriction\EndTimeRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\StartTimeRestriction;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

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

/**
 * Plugin 'Faceted search' for the 'ke_search' extension.
 * @author    Andreas Kiefer
 * @author    Stefan Froemken
 */
class TtContent extends Page
{
    public $indexedElementsName = 'content elements';

    /**
     * get content of current page and save data to db
     * @param $uid
     */
    public function getPageContent($uid)
    {
        $contentFields = GeneralUtility::trimExplode(',', $this->indexerConfig['content_fields'] ?: 'bodytext');

        // get content elements for this page
        $fields = '*';
        $table = 'tt_content';
        $queryBuilder = Db::getQueryBuilder($table);

        // don't index elements which are hidden or deleted, but do index
        // those with time restrictions, the time restrictions will be
        // copied to the index
        $queryBuilder->getRestrictions()
            ->removeByType(StartTimeRestriction::class)
            ->removeByType(EndTimeRestriction::class);

        // build array with where clauses
        $where = [];
        $where[] = $queryBuilder->expr()->eq(
            'pid',
            $queryBuilder->createNamedParameter(
                $uid,
                \PDO::PARAM_INT
            )
        );
        $where[] = $this->whereClauseForCType;

        // in incremental mode get only content elements which have been modified since last indexing time
        if ($this->indexingMode == self::INDEXING_MODE_INCREMENTAL) {
            $where[] = $queryBuilder->expr()->gte('tstamp', $this->lastRunStartTime);
        }

        // add condition for not indexing gridelement columns with colPos = -2 (= invalid)
        if (ExtensionManagementUtility::isLoaded('gridelements')) {
            $where[] = $queryBuilder->expr()->neq(
                'colPos',
                $queryBuilder->createNamedParameter(
                    -2,
                    \PDO::PARAM_INT
                )
            );
        }

        // Get access restrictions for this page
        $pageAccessRestrictions = $this->getInheritedAccessRestrictions($uid);

        $rows = $queryBuilder
            ->select($fields)
            ->from($table)
            ->where(...$where)
            ->executeQuery()
            ->fetchAllAssociative();

        if (count($rows)) {
            foreach ($rows as $row) {
                // skip this content element if the page itself is hidden or a
                // parent page with "extendToSubpages" set is hidden
                if ($pageAccessRestrictions['hidden']) {
                    continue;
                }

                // skip this content element if the page is hidden or set to "no_search"
                if (!$this->checkIfpageShouldBeIndexed($uid, $row['sys_language_uid'])) {
                    continue;
                }

                // combine group access restrictons from page(s) and content element
                $feGroups = $this->getCombinedFeGroupsForContentElement(
                    $pageAccessRestrictions['fe_group'],
                    $row['fe_group']
                );

                // skip this content element if either the page or the content
                // element is set to "hide at login"
                // and the other one has a frontend group attached to it
                if ($feGroups == DONOTINDEX) {
                    continue;
                }

                $logMessage = 'Indexing tt_content record';
                $logMessage .= $row['header'] ? ' "' . $row['header'] . '"' : '';
                $this->pObj->logger->debug($logMessage, [
                    'uid' => $row['uid'],
                    'pid' => $row['pid'],
                    'CType' => $row['CType'],
                ]);

                // get content for this content element
                $content = '';
                $fileObjects = [];

                // get tags from page
                $tags = $this->pageRecords[$uid]['tags'];

                // assign categories as tags (as cleartext, eg. "colorblue")
                $categories = SearchHelper::getCategories($row['uid'], $table);
                SearchHelper::makeTags($tags, $categories['title_list']);

                // assign categories as generic tags (eg. "syscat123")
                SearchHelper::makeSystemCategoryTags($tags, $row['uid'], $table);

                // index header
                // add header only if not set to "hidden"
                if ($row['header_layout'] != 100) {
                    $content .= strip_tags($row['header']) . "\n";
                }

                // index content of this content element and find attached or linked files.
                // Attached files are saved as file references, the RTE links directly to
                // a file, thus we get file objects.
                foreach ($contentFields as $field) {
                    $fileObjects = array_merge(
                        $fileObjects,
                        $this->findAttachedFiles($row),
                        $this->additionalContentService->findLinkedFiles($row, $field)
                    );
                    $content .= $this->getContentFromContentElement($row, $field) . "\n";
                }
                $additionalContentAndFiles = $this->additionalContentService->getContentAndFilesFromAdditionalTables($row);
                $content .= $additionalContentAndFiles['content'] . "\n";
                $fileObjects = array_merge($fileObjects, $additionalContentAndFiles['files']);

                // index the files found
                if (!empty($fileObjects)) {
                    $this->indexFiles($fileObjects, $row, $pageAccessRestrictions['fe_group'], $tags);
                }

                // Combine starttime and endtime from page, page language overlay
                // and content element.
                // TODO:
                // If current content element is a localized content
                // element, fetch startdate and enddate from original conent
                // element as the localized content element cannot have it's
                // own start- end enddate
                $starttime = $pageAccessRestrictions['starttime'];

                if ($this->cachedPageRecords[$row['sys_language_uid']][$row['pid']]['starttime'] > $starttime) {
                    $starttime = $this->cachedPageRecords[$row['sys_language_uid']][$row['pid']]['starttime'];
                }

                if ($row['starttime'] > $starttime) {
                    $starttime = $row['starttime'];
                }

                $endtime = $pageAccessRestrictions['endtime'];

                if ($endtime == 0 || ($this->cachedPageRecords[$row['sys_language_uid']][$row['pid']]['endtime']
                        && $this->cachedPageRecords[$row['sys_language_uid']][$row['pid']]['endtime'] < $endtime)) {
                    $endtime = $this->cachedPageRecords[$row['sys_language_uid']][$row['pid']]['endtime'];
                }

                if ($endtime == 0 || ($row['endtime'] && $row['endtime'] < $endtime)) {
                    $endtime = $row['endtime'];
                }

                // prepare additionalFields (to be added via hook)
                $additionalFields = [];

                // make it possible to modify the indexerConfig via hook
                $indexerConfig = $this->indexerConfig;

                // hook for custom modifications of the indexed data, e. g. the tags
                if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['modifyContentIndexEntry'] ?? null)) {
                    foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['modifyContentIndexEntry'] as
                             $_classRef) {
                        $_procObj = GeneralUtility::makeInstance($_classRef);
                        $_procObj->modifyContentIndexEntry(
                            $row['header'],
                            $row,
                            $tags,
                            $row['uid'],
                            $additionalFields,
                            $indexerConfig
                        );
                    }
                }

                // compile title from page title and content element title
                // TODO: make changeable via hook
                $title = $this->cachedPageRecords[$row['sys_language_uid']][$row['pid']]['title'];
                if ($row['header'] && $row['header_layout'] != 100) {
                    $title = $title . ' - ' . $row['header'];
                }

                // save record to index
                $this->pObj->storeInIndex(
                    $indexerConfig['storagepid'],        // storage PID
                    $title,                              // page title inkl. tt_content-title
                    'content',                           // content type
                    $row['pid'] . '#c' . $row['uid'],    // target PID: where is the single view?
                    $content,                            // indexed content, includes the title (linebreak after title)
                    $tags,                               // tags
                    '',                                  // typolink params for singleview
                    '',                                  // abstract
                    $row['sys_language_uid'],            // language uid
                    $starttime,                          // starttime
                    $endtime,                            // endtime
                    $feGroups,                           // fe_group
                    false,                               // debug only?
                    $additionalFields                    // additional fields added by hooks
                );

                // count elements written to the index
                $this->counter++;
            }
        } else {
            return;
        }

        return;
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

        /** @var TtContentRepository $ttContentRepository */
        $ttContentRepository = GeneralUtility::makeInstance(TtContentRepository::class);

        // get the pages from where to index the news
        $folders = $this->getPagelist(
            $this->indexerConfig['startingpoints_recursive'],
            $this->indexerConfig['sysfolder']
        );

        // Fetch all records which have been deleted or hidden since the last indexing
        $records = $ttContentRepository->findAllDeletedAndHiddenByPidListAndTimestampInAllLanguages($folders, $this->lastRunStartTime);

        // and remove the corresponding index entries
        $count = $indexRepository->deleteCorrespondingIndexRecords('content', $records, $this->indexerConfig);
        $message = chr(10) . 'Found ' . $count . ' deleted or hidden record(s).';
        return $message;
    }
}
