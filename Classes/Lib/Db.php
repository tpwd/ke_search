<?php

/** @noinspection PhpUndefinedClassInspection */
/** @noinspection PhpUndefinedNamespaceInspection */

namespace Tpwd\KeSearch\Lib;

use Doctrine\DBAL\Exception\DriverException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LogLevel;
use Tpwd\KeSearch\Domain\Search\SearchContextInterface;
use Tpwd\KeSearch\Event\MatchColumnsEvent;
use Tpwd\KeSearch\Plugins\PluginBase;
use Tpwd\KeSearchPremium\KeSearchPremium;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\LanguageAspect;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Log\Logger;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/***************************************************************
 *  Copyright notice
 *  (c) 2011 Stefan Froemken
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
 * Database access layer for ke_search: builds and runs search queries against the index.
 *
 * This class is responsible for:
 * - Building SQL query parts (SELECT, FROM, WHERE, GROUP BY, ORDER BY, HAVING, JOIN) from the
 *   current search context (search phrase, filters, sorting, date range, etc.)
 * - Executing the search either via MySQL against tx_kesearch_index or via Sphinx when
 *   ke_search_premium is used (sphinxSearchEnabled())
 * - Returning paginated result sets and the total number of results
 * - Extracting and aggregating tags from the result set (for filter facets)
 * - Applying configuration from the plugin/search context (visibility, language, fe_groups)
 *
 * A SearchContextInterface (e.g. PluginBase) must be set via setSearchContext() before running
 * searches. The class is not a singleton; frontend plugins share one instance per request via
 * SearchDbRegistry.
 *
 * @author Stefan Froemken
 */
class Db
{
    public const DEFAULT_MATCH_COLUMS = 'title,content,hidden_content';

    public array $conf = [];
    public int $countResultsOfTags = 0;
    public int $countResultsOfContent = 0;
    public string $table = 'tx_kesearch_index';
    protected bool $hasSearchResults = true;
    protected array $searchResults = [];
    protected int $numberOfResults = 0;
    protected KeSearchPremium $keSearchPremium;
    protected array $errors = [];
    private EventDispatcherInterface $eventDispatcher;
    public SearchContextInterface $searchContext;

    /**
     * @deprecated Use $searchContext instead. Set automatically when context is PluginBase.
     */
    public ?PluginBase $pObj = null;

    public function __construct(EventDispatcherInterface $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    public function setSearchContext(SearchContextInterface $searchContext): void
    {
        $this->searchContext = $searchContext;
        $this->pObj = $searchContext instanceof PluginBase ? $searchContext : null;
        // @extensionScannerIgnoreLine
        $this->conf = $this->searchContext->getConf();
    }

    /**
     * @deprecated Use setSearchContext() instead. Will be removed in a future version.
     */
    public function setPluginbase(PluginBase $pObj): void
    {
        $this->setSearchContext($pObj);
    }

    /**
     * Returns the search result for the current search parameters, either using the default search
     * based on MySQL or based on Sphinx (ke_search_premium feature).
     * If there is a cached result list, it is returned directly without executing the search; otherwise
     * search is executed.
     *
     * @return array
     */
    public function getSearchResults()
    {
        // if there are no search results, return the empty result array directly
        if (!$this->hasSearchResults) {
            return $this->searchResults;
        }

        if (!count($this->searchResults)) {
            if ($this->sphinxSearchEnabled()) {
                $this->searchResults = $this->getSearchResultBySphinx();
            } else {
                $this->getSearchResultByMySQL();
            }
            if ($this->getAmountOfSearchResults() === 0) {
                $this->hasSearchResults = false;
            }
        }
        return $this->searchResults;
    }

    /**
     * Builds the search result query from query parts (SELECT, FROM, WHERE, GROUP BY, ORDER BY, HAVING, JOIN).
     * Does not apply LIMIT; callers can set limit and execute.
     *
     * @param array $queryParts Result of getQueryParts() (SELECT may be overridden by caller)
     * @return QueryBuilder
     */
    private function buildSearchResultQuery(array $queryParts): QueryBuilder
    {
        $queryBuilder = self::getQueryBuilder($this->table);
        $queryBuilder->getRestrictions()->removeAll();
        $resultQuery = $queryBuilder
            ->selectLiteral($queryParts['SELECT'])
            ->from($queryParts['FROM'])
            ->where($queryParts['WHERE']);

        if (!empty($queryParts['GROUPBY'])) {
            $groupParts = explode(',', $queryParts['GROUPBY']);
            $resultQuery->groupBy($groupParts[0], $groupParts[1]);
        }
        if (!empty($queryParts['ORDERBY'])) {
            $orderChain = explode(',', $queryParts['ORDERBY']);
            $count = 0;
            foreach ($orderChain as $order) {
                $orderParts = explode(' ', trim($order));
                $orderField = strtoupper($orderParts[0]);
                $orderDirection = strtoupper($orderParts[1] ?? 'ASC');
                if ($count === 0) {
                    if (
                        ExtensionManagementUtility::isLoaded('ke_search_premium')
                        && ($orderField === strtoupper('customranking'))
                    ) {
                        // We cast `customranking` to integer because additionalFields in ke_search can only
                        // be string, so we cannot use an integer field, although it's a numeric value (can also be
                        // negative).
                        $resultQuery->getConcreteQueryBuilder()->orderBy(
                            'CAST(' . $this->table . '.' . $queryBuilder->quoteIdentifier($orderField) . ' AS SIGNED)',
                            $orderDirection
                        );
                    } else {
                        $resultQuery->orderBy($orderField, $orderDirection);
                    }
                } else {
                    if (
                        ExtensionManagementUtility::isLoaded('ke_search_premium')
                        && ($orderField === strtoupper('customranking'))
                    ) {
                        $resultQuery->getConcreteQueryBuilder()->addOrderBy(
                            'CAST(' . $this->table . '.' . $queryBuilder->quoteIdentifier($orderField) . ' AS SIGNED)',
                            $orderDirection
                        );
                    } else {
                        $resultQuery->addOrderBy($orderField, $orderDirection);
                    }
                }
                $count++;
            }
        }
        if (!empty($queryParts['HAVING'])) {
            $resultQuery->having($queryParts['HAVING']);
        }
        if (!empty($queryParts['JOIN']) && is_array($queryParts['JOIN'])) {
            foreach ($queryParts['JOIN'] as $table => $condition) {
                $resultQuery->join(
                    $this->table,
                    $table,
                    $table,
                    $condition ?: null,
                );
            }
        }

        return $resultQuery;
    }

    /**
     * get a limited number of search results for a requested page
     */
    public function getSearchResultByMySQL()
    {
        /** @var LogManager $logManager */
        $logManager = GeneralUtility::makeInstance(LogManager::class);
        /** @var Logger $logger */
        $logger = $logManager->getLogger(__CLASS__);

        $queryParts = $this->getQueryParts();
        $resultQuery = $this->buildSearchResultQuery($queryParts);

        $limit = $this->getLimit();
        $resultQuery->setMaxResults($limit[1]);
        $resultQuery->setFirstResult($limit[0]);

        // execute query
        try {
            $this->searchResults = $resultQuery->executeQuery()->fetchAllAssociative();
        } catch (DriverException $driverException) {
            $logger->log(LogLevel::ERROR, $driverException->getMessage() . ' ' . $driverException->getTraceAsString());
            $this->addError('Could not fetch search results. Error #1605867846');
            $this->searchResults = [];
            $this->numberOfResults = 0;
        }

        // log query
        if ($this->conf['logQuery'] ?? false) {
            $logger->log(LogLevel::DEBUG, $resultQuery->getSQL());
        }

        // fetch number of results
        if (!empty($this->searchResults)) {
            $queryBuilder = self::getQueryBuilder('tx_kesearch_index');
            $queryBuilder->getRestrictions()->removeAll();
            $numRows = $queryBuilder
                ->selectLiteral('FOUND_ROWS()')
                ->executeQuery()
                ->fetchNumeric()[0];
            $this->numberOfResults = $numRows;
        }
    }

    /**
     * Escpapes Query String for Sphinx, taken from SphinxApi.php
     *
     * @param string $string
     * @return string
     */
    public function escapeString($string)
    {
        $from = ['\\', '(', ')', '-', '!', '@', '~', '"', '&', '/', '^', '$', '='];
        $to = ['\\\\', '\(', '\)', '\-', '\!', '\@', '\~', '\"', '\&', '\/', '\^', '\$', '\='];

        return str_replace($from, $to, $string);
    }

    /**
     * Get the search result from Sphinx. Returns either a limited (one page) result (if $limitToOnePage is
     * set to true) or a result for all pages (useful to calculate the tags in the complete result set).
     *
     * @return array search results
     */
    public function getSearchResultBySphinx(bool $limitToOnePage = true): array
    {
        if (!class_exists(KeSearchPremium::class)) {
            return [];
        }

        $this->keSearchPremium = GeneralUtility::makeInstance(KeSearchPremium::class);

        // set ordering
        $this->keSearchPremium->setSorting($this->getOrdering());

        // set limit
        if ($limitToOnePage) {
            $limit = $this->getLimit();
            $this->keSearchPremium->setLimit(
                $limit[0],
                $limit[1],
                (int)($this->searchContext->getExtConfPremium()['sphinxLimit'] ?? 0)
            );
        } else {
            $this->keSearchPremium->setLimit(
                0,
                (int)($this->searchContext->getExtConfPremium()['sphinxLimit'] ?? 0),
                (int)($this->searchContext->getExtConfPremium()['sphinxLimit'] ?? 0)
            );
        }

        // generate query
        $queryForSphinx = '';
        if ($this->searchContext->getWordsAgainst()) {
            $queryForSphinx .= ' @(title,content) ' . $this->escapeString($this->searchContext->getWordsAgainst());
        }
        if (count($this->searchContext->getTagsAgainst())) {
            foreach ($this->searchContext->getTagsAgainst() as $value) {
                // in normal case only checkbox mode has spaces
                $queryForSphinx .= ' @tags ' . str_replace('" "', '" | "', trim($value));
            }
        }

        // add language
        /** @var LanguageAspect $languageAspect */
        $languageAspect = GeneralUtility::makeInstance(Context::class)->getAspect('language');
        $queryForSphinx .= ' @language _language_-1 | _language_' . $languageAspect->getId();

        // add fe_groups to query
        $queryForSphinx .= ' @fe_group _group_NULL | _group_0';

        /** @var Context $context */
        $context = GeneralUtility::makeInstance(Context::class);
        $feGroups = $context->getPropertyFromAspect('frontend.user', 'groupIds');
        if (count($feGroups)) {
            foreach ($feGroups as $key => $group) {
                $intval_positive_group = max(0, (int)$group);
                if ($intval_positive_group) {
                    $feGroups[$key] = '_group_' . $group;
                } else {
                    unset($feGroups[$key]);
                }
            }
            if (is_array($feGroups) && count($feGroups)) {
                $queryForSphinx .= ' | ' . implode(' | ', $feGroups);
            }
        }

        // restrict to storage page (in MySQL: $where .= ' AND pid in (' .  . ') ';)
        $startingPoints = GeneralUtility::trimExplode(',', $this->searchContext->getStartingPoints());
        $queryForSphinx .= ' @pid ';
        $first = true;
        foreach ($startingPoints as $startingPoint) {
            if (!$first) {
                $queryForSphinx .= ' | ';
            } else {
                $first = false;
            }

            $queryForSphinx .= ' _pid_' . $startingPoint;
        }

        // hook for appending additional where clause to sphinx query
        if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['appendWhereToSphinx'] ?? null)) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['appendWhereToSphinx'] as $_classRef) {
                $_procObj = GeneralUtility::makeInstance($_classRef);
                $queryForSphinx = $_procObj->appendWhereToSphinx($queryForSphinx, $this->keSearchPremium, $this);
            }
        }
        $rows = $this->keSearchPremium->getSearchResults($queryForSphinx);

        // get number of records
        $this->numberOfResults = $this->keSearchPremium->getTotalFound();
        return $rows;
    }

    /**
     * get query parts like SELECT, FROM and WHERE for MySQL-Query
     *
     * @return array Array containing the query parts for MySQL
     */
    public function getQueryParts()
    {
        $databaseConnection = self::getDatabaseConnection($this->table);
        $searchwordQuoted = $databaseConnection->quote((string)$this->searchContext->getScoreAgainst());
        $limit = $this->getLimit();
        $queryParts = [
            'SELECT' => $this->getFields($searchwordQuoted),
            'FROM' => $this->table,
            'JOIN' => null,
            'WHERE' => '1=1' . $this->getWhere(),
            'GROUPBY' => '',
            'ORDERBY' => $this->getOrdering(),
            'LIMIT' => $limit[0] . ',' . $limit[1],
        ];

        // hook for third party applications to manipulate last part of query building
        if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['getQueryParts'] ?? null)) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['getQueryParts'] as $_classRef) {
                $_procObj = GeneralUtility::makeInstance($_classRef);
                $queryParts = $_procObj->getQueryParts($queryParts, $this, $searchwordQuoted);
            }
        }

        return $queryParts;
    }

    /**
     * Counts the search results
     * It's better to make an additional query than working with
     * SQL_CALC_FOUND_ROWS. Further we don't have to lock tables.
     *
     * @return int Amount of SearchResults
     */
    public function getAmountOfSearchResults(): int
    {
        return (int)($this->numberOfResults);
    }

    /**
     * Returns UIDs of all search results (no pagination, no full row data).
     * Efficient for RAG/API use cases where only index UIDs are needed.
     *
     * @return list<int> UIDs from tx_kesearch_index
     */
    public function getSearchResultUids(): array
    {
        if ($this->sphinxSearchEnabled()) {
            $rows = $this->getSearchResultBySphinx(false);
            return array_map('intval', array_column($rows, 'uid'));
        }

        $queryParts = $this->getQueryParts();
        $queryParts['SELECT'] = $this->table . '.uid';
        $resultQuery = $this->buildSearchResultQuery($queryParts);

        try {
            $rows = $resultQuery->executeQuery()->fetchAllAssociative();
        } catch (DriverException $e) {
            return [];
        }

        return array_map('intval', array_column($rows, 'uid'));
    }

    /**
     * get all tags which are found in search result
     * additional the tags are counted
     *
     * @return array Array containing the tags as key and the sum as value
     */
    public function getTagsFromSearchResult(): array
    {
        $tags = [];
        $tagChar = $this->searchContext->getExtConf()['prePostTagChar'];
        $tagDivider = $tagChar . ',' . $tagChar;

        if ($this->sphinxSearchEnabled()) {
            $tagsForResult = $this->getTagsFromSphinx();
        } else {
            $tagsForResult = $this->getTagsFromMySQL();
        }
        foreach ($tagsForResult as $tagSet) {
            $tagSet = explode($tagDivider, trim($tagSet, $tagChar));
            foreach ($tagSet as $tag) {
                if (!isset($tags[$tag])) {
                    $tags[$tag] = 0;
                }
                $tags[$tag] += 1;
            }
        }
        return $tags;
    }

    /**
     * Determine the available tags for the search result by looking at
     * all the tag fields
     *
     * @return array
     */
    protected function getTagsFromSphinx()
    {
        $searchResultUnlimited = $this->getSearchResultBySphinx(false);
        if (count($searchResultUnlimited)) {
            return array_map(
                function ($row) {
                    return $row['tags'];
                },
                $searchResultUnlimited
            );
        } else {
            return [];
        }
    }

    /**
     * Determine the valid tags by querying MySQL
     *
     * @return array
     */
    protected function getTagsFromMySQL()
    {
        $queryParts = $this->getQueryParts();

        $queryBuilder = self::getQueryBuilder('tx_kesearch_index');
        $queryBuilder->getRestrictions()->removeAll();

        $resultQuery = $queryBuilder
            ->select('tx_kesearch_index.tags')
            ->from($queryParts['FROM'])
            ->where($queryParts['WHERE']);

        $tagRows = $resultQuery->executeQuery()->fetchAllAssociative();

        return array_map(
            function ($row) {
                return $row['tags'];
            },
            $tagRows
        );
    }

    /**
     * In checkbox mode we have to create for each checkbox one MATCH-AGAINST-Construct
     * So this function returns the complete WHERE-Clause for each tag
     *
     * @param array $tags
     * @return string Query
     */
    protected function createQueryForTags(array $tags)
    {
        $databaseConnection = self::getDatabaseConnection('tx_kesearch_index');
        $where = '';
        if (count($tags)) {
            foreach ($tags as $value) {
                $quotedValue = $databaseConnection->quote((string)$value);
                $where .= ' AND MATCH (tx_kesearch_index.tags) AGAINST (' . $quotedValue . ' IN BOOLEAN MODE) ';
            }
            return $where;
        }
        return '';
    }

    /**
     * @return string
     */
    private function getMatchColumns(): string
    {
        /** @var MatchColumnsEvent $matchColumnsEvent */
        $matchColumnsEvent = $this->eventDispatcher->dispatch(
            new MatchColumnsEvent(self::DEFAULT_MATCH_COLUMS)
        );
        return $matchColumnsEvent->getMatchColumns();
    }

    /**
     * @return string
     */
    public function getFields(string $searchwordQuoted): string
    {
        $fields = 'SQL_CALC_FOUND_ROWS tx_kesearch_index.*';

        // if a searchword was given, calculate score
        if ($this->searchContext->getSword()) {
            $fields .=
                ', MATCH (' . $this->getMatchColumns() . ') AGAINST (' . $searchwordQuoted . ')'
                . '+ ('
                . $this->searchContext->getExtConf()['multiplyValueToTitle']
                . ' * MATCH (tx_kesearch_index.title) AGAINST (' . $searchwordQuoted . ')'
                . ') AS score';
        }

        return $fields;
    }

    /**
     * get where clause for search results
     *
     * @return string where clause
     */
    public function getWhere()
    {
        $where = '';

        $databaseConnection = self::getDatabaseConnection('tx_kesearch_index');
        $wordsAgainstQuoted = $databaseConnection->quote((string)$this->searchContext->getWordsAgainst());

        // add boolean where clause for searchwords
        if ($this->searchContext->getWordsAgainst() != '') {
            $where .= ' AND MATCH (' . $this->getMatchColumns() . ') AGAINST (';
            $where .= $wordsAgainstQuoted . ' IN BOOLEAN MODE) ';
        }

        // add boolean where clause for tags
        if (($tagWhere = $this->createQueryForTags($this->searchContext->getTagsAgainst()))) {
            $where .= $tagWhere;
        }

        $where .= $this->createQueryForDateRange();

        // restrict to storage page
        if (empty($this->searchContext->getStartingPoints())) {
            throw new \Exception('No starting point found. Please set the starting point in the plugin'
                . ' configuration or via TypoScript: '
                . 'https://docs.typo3.org/p/tpwd/ke_search/main/en-us/Configuration/OverrideRecordStoragePage.html.');
        }
        $startingPoints = $this->searchContext->pi_getPidList($this->searchContext->getStartingPoints());
        $where .= ' AND tx_kesearch_index.pid in (' . $startingPoints . ') ';

        // add language
        /** @var LanguageAspect $languageAspect */
        $languageAspect = GeneralUtility::makeInstance(Context::class)->getAspect('language');
        $where .= ' AND tx_kesearch_index.language IN(' . $languageAspect->getId() . ', -1) ';

        // add "tagged content only" searchphrase
        if ($this->conf['showTaggedContentOnly'] ?? false) {
            $where .= ' AND tx_kesearch_index.tags <> ""';
        }

        // add enable fields
        /** @var PageRepository $pageRepository */
        $pageRepository = GeneralUtility::makeInstance(PageRepository::class);
        $constraints = $pageRepository->getDefaultConstraints($this->table);
        $expressionBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable($this->table)
            ->expr();
        $where .= ' AND ' . $expressionBuilder->and(...$constraints);

        return $where;
    }

    public function createQueryForDateRange()
    {
        $where = '';
        $filters = $this->searchContext->getFilters()->getFilters();
        if (!empty($filters)) {
            foreach ($filters as $filterUid => $filter) {
                if (
                    $filter['rendertype'] == 'dateRange'
                    && isset($this->searchContext->getPiVars()['filter'][$filterUid])
                ) {
                    $filterValues = $this->searchContext->getPiVars()['filter'][$filterUid];
                    $startTimestamp = strtotime($filterValues['start'] ?? '');
                    $endTimestamp = strtotime($filterValues['end'] ?? '');
                    if ($startTimestamp) {
                        $where .= ' AND tx_kesearch_index.sortdate >= ' . $startTimestamp;
                    }
                    if ($endTimestamp) {
                        $where .= ' AND tx_kesearch_index.sortdate <= ' . ($endTimestamp + 24 * 60 * 60);
                    }
                }
            }
        }
        return $where;
    }

    /**
     * get ordering for where query
     *
     * @return string ordering (f.e. score DESC)
     */
    public function getOrdering()
    {
        // If the following code fails, fall back to this default ordering
        $orderBy = $this->conf['sortWithoutSearchword'];

        // If sorting in FE is allowed
        if ($this->conf['showSortInFrontend'] ?? false) {
            $piVarsField = $this->searchContext->getPiVars()['sortByField'] ?? '';
            $piVarsDir = $this->searchContext->getPiVars()['sortByDir'] ?? '';
            $piVarsDir = ($piVarsDir == '') ? 'asc' : $piVarsDir;

            // Check if an ordering field is defined by GET/POST and use that.
            // If sortByVisitor is not set OR not in the list of
            // allowed fields then use fallback ordering in "sortWithoutSearchword"
            if (!empty($piVarsField)) {
                $isInList = GeneralUtility::inList($this->conf['sortByVisitor'], $piVarsField);
                if ($this->conf['sortByVisitor'] != '' && $isInList) {
                    $orderBy = $piVarsField . ' ' . $piVarsDir;
                }
            }
        } else {
            // If sorting is predefined by admin
            if (!empty($this->searchContext->getWordsAgainst())) {
                $orderBy = $this->conf['sortByAdmin'];
            }
        }

        // hook for third party extensions to modify the sorting
        if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['getOrdering'] ?? null)) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['getOrdering'] as $_classRef) {
                $_procObj = GeneralUtility::makeInstance($_classRef);
                $_procObj->getOrdering($orderBy, $this);
            }
        }

        return $orderBy;
    }

    /**
     * get limit for where query
     *
     * @return array
     */
    public function getLimit()
    {
        $start = 0;
        $limit = ($this->conf['resultsPerPage'] ?? 10) ?: 10;

        if ($this->searchContext->getPiVars()['page'] ?? false) {
            $start = ($this->searchContext->getPiVars()['page'] * $limit) - $limit;
            if ($start < 0) {
                $start = 0;
            }
        }

        $startLimit = [$start, $limit];

        // hook for third party page browsers or for modification of the $this->searchContext->getPiVars()['page'] parameter
        if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['getLimit'] ?? null)) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['getLimit'] as $_classRef) {
                $_procObj = GeneralUtility::makeInstance($_classRef);
                $_procObj->getLimit($startLimit, $this);
            }
        }

        return $startLimit;
    }

    /**
     * Check if Sphinx search is enabled
     *
     * @return  bool
     */
    protected function sphinxSearchEnabled()
    {
        return ($this->searchContext->getExtConfPremium()['enableSphinxSearch'] ?? false) && !$this->searchContext->getIsEmptySearch();
    }

    /**
     * Returns the query builder for the database connection.
     *
     * @param string $table
     * @return QueryBuilder
     */
    public static function getQueryBuilder($table)
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
        return $queryBuilder;
    }

    /**
     * Returns the database connection.
     *
     * @param string $table
     * @return Connection
     */
    public static function getDatabaseConnection($table)
    {
        $databaseConnection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable($table);
        return $databaseConnection;
    }

    /**
     * @param string $message
     */
    public function addError(string $message)
    {
        $this->errors[] = $message;
    }

    /**
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }
}
