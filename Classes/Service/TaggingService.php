<?php

namespace Tpwd\KeSearch\Service;

use Tpwd\KeSearch\Lib\Db;
use Tpwd\KeSearch\Lib\SearchHelper;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Log\Logger;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Service for handling tagging of records during indexing.
 *
 * This service provides methods to add tags to page records based on:
 * 1. Page properties (tx_kesearch_tags)
 * 2. System categories
 * 3. Automated tagging rules defined in filter options
 */
class TaggingService
{
    protected TreeService $treeService;
    protected Logger $logger;

    /**
     * TaggingService constructor.
     *
     * @param TreeService $treeService
     * @param LogManager $logManager
     */
    public function __construct(
        TreeService $treeService,
        LogManager $logManager
    ) {
        $this->treeService = $treeService;
        $this->logger = $logManager->getLogger(__CLASS__);
    }

    /**
     * Entry point for adding tags to an array of page records ("automated tagging").
     * Tags are created from
     * - ke_search tags directly assigned to pages
     * - system categories assigned to pages
     * - filter options which have `automated_tagging` configured
     *
     * @param array $pageRecords The records to add tags to (indexed by UID)
     * @param array $uids Simple array with uids of pages to process
     * @param string $tagChar Character used to wrap tags (e.g., # or _)
     * @param string $pageWhere Additional where-clause for automated tagging
     * @return array Modified page records
     */
    public function addTagsToPageRecords(array $pageRecords, array $uids, string $tagChar, string $pageWhere = ''): array
    {
        if (empty($uids)) {
            $this->logger->warning('No pages/sysfolders given to add tags for.');
            return $pageRecords;
        }

        // 1. Add tags which are defined by page properties (tx_kesearch_tags)
        $pageRecords = $this->addTagsFromPageProperties($pageRecords, $uids, $tagChar);

        // 2. Add system categories as tags
        foreach ($uids as $pageUid) {
            if (isset($pageRecords[$pageUid])) {
                SearchHelper::makeSystemCategoryTags($pageRecords[$pageUid]['tags'], (int)$pageUid, 'pages');
            }
        }

        // 3. Add tags which are defined by filter option records (automated tagging)
        $pageRecords = $this->addAutomatedTags($pageRecords, $tagChar, $pageWhere);

        return $pageRecords;
    }

    /**
     * Fetches tags from the 'tx_kesearch_tags' field in the 'pages' table.
     * These are tags manually assigned to pages in their page properties.
     *
     * @param array $pageRecords
     * @param array $uids
     * @param string $tagChar
     * @return array
     */
    protected function addTagsFromPageProperties(array $pageRecords, array $uids, string $tagChar): array
    {
        $queryBuilder = Db::getQueryBuilder('tx_kesearch_filteroptions');
        $queryBuilder
            ->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
            ->add(GeneralUtility::makeInstance(HiddenRestriction::class));

        // Ensure all UIDs are integers to prevent SQL injection
        $uids = array_map('intval', $uids);

        // Build the fields part of the query. We use GROUP_CONCAT to get all tags for a page in one string.
        $fields = 'pages.uid, GROUP_CONCAT(CONCAT('
            . $queryBuilder->createNamedParameter($tagChar)
            . ', tx_kesearch_filteroptions.tag, '
            . $queryBuilder->createNamedParameter($tagChar)
            . ')) as tags';

        $queryBuilder
            ->selectLiteral($fields)
            ->from('pages')
            ->from('tx_kesearch_filteroptions')
            ->where(
                $queryBuilder->expr()->in(
                    'pages.uid',
                    $queryBuilder->createNamedParameter($uids, Connection::PARAM_INT_ARRAY)
                ),
                $queryBuilder->expr()->neq(
                    'pages.tx_kesearch_tags',
                    $queryBuilder->createNamedParameter('', Connection::PARAM_STR)
                ),
                'FIND_IN_SET(tx_kesearch_filteroptions.uid, pages.tx_kesearch_tags)'
            )
            ->groupBy('pages.uid');

        $result = $queryBuilder->executeQuery();

        while ($row = $result->fetchAssociative()) {
            if (isset($pageRecords[$row['uid']])) {
                $pageRecords[$row['uid']]['tags'] = $row['tags'];
            }
        }

        return $pageRecords;
    }

    /**
     * Processes "Automated Tagging" rules defined in filter options.
     * For each filter option with automated tagging enabled, it finds all pages
     * within the specified starting points and applies the tag.
     *
     * @param array $pageRecords
     * @param string $tagChar
     * @param string $pageWhere
     * @return array
     */
    protected function addAutomatedTags(array $pageRecords, string $tagChar, string $pageWhere = ''): array
    {
        $queryBuilder = Db::getQueryBuilder('tx_kesearch_filteroptions');
        // Fetch all filter options that have automated tagging configured
        $filterOptionsRows = $queryBuilder
            ->select('automated_tagging', 'automated_tagging_exclude', 'tag')
            ->from('tx_kesearch_filteroptions')
            ->where(
                $queryBuilder->expr()->neq(
                    'automated_tagging',
                    $queryBuilder->createNamedParameter('', Connection::PARAM_STR)
                )
            )
            ->executeQuery()
            ->fetchAllAssociative();

        $baseWhere = 'no_search <> 1';
        if (!empty($pageWhere)) {
            $baseWhere = $pageWhere . ' AND ' . $baseWhere;
        }

        foreach ($filterOptionsRows as $row) {
            $currentWhere = $baseWhere;
            // Handle excluded PIDs for this specific automated tagging rule
            if (!empty($row['automated_tagging_exclude'])) {
                // automated_tagging_exclude is a comma-separated list of PIDs
                $excludePids = GeneralUtility::intExplode(',', $row['automated_tagging_exclude'], true);
                if (!empty($excludePids)) {
                    $currentWhere .= ' AND pages.pid NOT IN (' . implode(',', $excludePids) . ')';
                }
            }

            // The field `automated_tagging` contains a comma-separated list of starting PIDs
            $automatedTaggingPids = GeneralUtility::intExplode(',', $row['automated_tagging'], true);
            $pageList = [];
            foreach ($automatedTaggingPids as $pid) {
                // Get all subpages for each starting PID
                $treeList = $this->treeService->getTreeList($pid, 99, 0, $currentWhere);
                $tmpPageList = GeneralUtility::trimExplode(',', $treeList, true);
                $pageList = array_merge($pageList, $tmpPageList);
            }

            // Remove duplicates as multiple starting points might overlap
            $pageList = array_unique($pageList);

            // Apply the tag to all pages found in the tree
            foreach ($pageList as $uid) {
                if (isset($pageRecords[$uid])) {
                    $pageRecords[$uid]['tags'] = SearchHelper::addTag($row['tag'], $pageRecords[$uid]['tags']);
                }
            }
        }

        return $pageRecords;
    }
}
