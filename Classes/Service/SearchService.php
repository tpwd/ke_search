<?php

declare(strict_types=1);

namespace Tpwd\KeSearch\Service;

use Psr\EventDispatcher\EventDispatcherInterface;
use Tpwd\KeSearch\Domain\Search\SearchExecutionContext;
use Tpwd\KeSearch\Domain\Search\SearchRequest;
use Tpwd\KeSearch\Domain\Search\SearchResult;
use Tpwd\KeSearch\Lib\Db;
use Tpwd\KeSearch\Lib\Filters;
use Tpwd\KeSearch\Lib\SearchHelper;
use Tpwd\KeSearch\Lib\Searchphrase;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/***************************************************************
 *  Copyright notice
 *  All rights reserved
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 ***************************************************************/

/**
 * Public API to execute a ke_search search from extensions (e.g. ke_search_premium).
 *
 * Uses a dedicated Db instance (not the singleton) so that API searches do not overwrite
 * the plugin's search results when called from within a hook.
 *
 * Usage:
 *   $result = $searchService->search(SearchRequest::fromArray([
 *       'searchWord' => '…',
 *       'startingPoints' => '1,2',
 *       'limit' => 10,
 *   ]));
 *
 * For RAG / same search with empty phrase (e.g. in a hook with access to the plugin):
 *   $ragRequest = SearchRequest::fromContext($pObj)->withEmptySearchPhrase();
 *   $ragResult = $searchService->search($ragRequest);
 *
 * For RAG when you only need all result UIDs (no pagination, efficient):
 *   $uids = $searchService->searchUids(SearchRequest::fromContext($pObj)->withEmptySearchPhrase());
 */
class SearchService
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher
    ) {}

    public function search(SearchRequest $searchRequest): SearchResult
    {
        [$context, $db] = $this->buildContextAndDb($searchRequest);
        $this->buildSearchPhrase($context);
        $results = $db->getSearchResults();
        $totalCount = $db->getAmountOfSearchResults();
        return new SearchResult($results, $totalCount);
    }

    /**
     * Execute search and return UIDs of all matching index records (no pagination, no full rows).
     * Efficient for RAG/API when only UIDs are needed.
     *
     * @return list<int> UIDs from tx_kesearch_index
     */
    public function searchUids(SearchRequest $request): array
    {
        [$context, $db] = $this->buildContextAndDb($request);
        $this->buildSearchPhrase($context);
        return $db->getSearchResultUids();
    }

    /**
     * @return array{0: SearchExecutionContext, 1: Db}
     */
    private function buildContextAndDb(SearchRequest $searchRequest): array
    {
        $extConf = SearchHelper::getExtConf();
        $extConfPremium = SearchHelper::getExtConfPremium();
        $conf = $searchRequest->getConf();
        if ($conf === []) {
            $conf = [
                'resultsPerPage' => $searchRequest->getLimit() ?? 10,
                'hiddenfilters' => '',
                'filters' => '',
            ];
        }
        if (!isset($conf['resultsPerPage']) && $searchRequest->getLimit() !== null) {
            $conf['resultsPerPage'] = $searchRequest->getLimit();
        }
        if (!isset($conf['resultsPerPage'])) {
            $conf['resultsPerPage'] = 10;
        }

        $context = new SearchExecutionContext();
        $context->setConf($conf);
        $context->setExtConf($extConf);
        $context->setExtConfPremium($extConfPremium);
        $context->setPreselectedFilter($conf['preselectedFilter'] ?? []);

        $piVars = [
            'sword' => $searchRequest->getSearchWord(),
            'filter' => $searchRequest->getFilter(),
            'page' => $searchRequest->getPage(),
            'sortByField' => $searchRequest->getSortByField(),
            'sortByDir' => $searchRequest->getSortByDir(),
        ];
        $context->setPiVars($piVars);
        $context->setStartingPoints($searchRequest->getStartingPoints());

        $db = new Db($this->eventDispatcher);
        $db->setSearchContext($context);
        $context->setDb($db);

        if ($searchRequest->getRequest() !== null) {
            $context->setRequest($searchRequest->getRequest());
        }

        $filters = GeneralUtility::makeInstance(Filters::class);
        $filters->initialize($context);
        $context->setFilters($filters);

        return [$context, $db];
    }

    private function buildSearchPhrase(SearchExecutionContext $context): void
    {
        $searchPhrase = GeneralUtility::makeInstance(Searchphrase::class);
        $searchPhrase->initialize($context);
        $phraseResult = $searchPhrase->buildSearchPhrase();

        $context->setSword($phraseResult['sword']);
        $context->setWordsAgainst($phraseResult['wordsAgainst']);
        $context->setScoreAgainst($phraseResult['scoreAgainst']);
        $context->setTagsAgainst($phraseResult['tagsAgainst']);
        $context->setIsEmptySearch(trim($phraseResult['sword']) === '');

        if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['modifySearchWords'] ?? null)) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['modifySearchWords'] as $classRef) {
                $hookObj = GeneralUtility::makeInstance($classRef);
                if (method_exists($hookObj, 'modifySearchWords')) {
                    $hookObj->modifySearchWords($phraseResult, $context);
                }
            }
            $context->setSword($phraseResult['sword']);
            $context->setWordsAgainst($phraseResult['wordsAgainst'] ?? $context->getWordsAgainst());
            $context->setScoreAgainst($phraseResult['scoreAgainst'] ?? $context->getScoreAgainst());
            $context->setTagsAgainst($phraseResult['tagsAgainst'] ?? $context->getTagsAgainst());
        }
    }
}
