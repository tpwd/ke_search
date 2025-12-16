<?php

namespace Tpwd\KeSearch\Lib;

/***************************************************************
 *  Copyright notice
 *  (c) 2012 Stefan Froemken
 *  All rights reserved
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *  The GNU General Public License can be found at
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use Tpwd\KeSearch\Plugins\PluginBase;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\LanguageAspect;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Type\Bitmask\PageTranslationVisibility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\StringUtility;

/**
 * Class Filters is responsible for managing filters and their options, including
 * retrieval, selection, and configuration based on different input sources like frontend or backend.
 */
class Filters
{
    /**
     * @var PluginBase
     */
    protected $pObj;

    /**
     * @var Db
     */
    protected Db $db;

    protected $tagChar = '#';
    protected $filters = [];
    protected $conf = [];
    protected $piVars = [];
    protected $extConf = [];
    protected $extConfPremium = [];

    /**
     * contains all tags of the current search result, false if not initialized yet
     * @var bool|array
     */
    protected $tagsInSearchResult = false;

    protected $startingPoints = '';

    /**
     * Initializes this object
     * @param PluginBase $pObj
     */
    public function initialize(PluginBase $pObj)
    {
        $this->pObj = $pObj;
        $this->db = $this->pObj->db;
        // @extensionScannerIgnoreLine
        $this->conf = $this->pObj->conf;
        $this->piVars = $this->pObj->piVars;
        $this->startingPoints = $this->pObj->startingPoints;
        $this->tagChar = $this->pObj->extConf['prePostTagChar'];

        // get filters and filter options
        $this->filters = $this->getFiltersFromUidList(
            $this->combineLists($this->conf['filters'] ?? '', $this->conf['hiddenfilters'] ?? '')
        );

        // hook to modify filters
        if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['modifyFilters'] ?? null)) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['modifyFilters'] as $_classRef) {
                $_procObj = GeneralUtility::makeInstance($_classRef);
                $_procObj->modifyFilters($this->filters, $this);
            }
        }

        // get list of selected filter options (via frontend or backend)
        foreach ($this->filters as $filter) {
            $this->filters[$filter['uid']]['selectedOptions'] = $this->getSelectedFilterOptions($filter);
        }
    }

    /**
     * Finds the selected filter options for a given filter.
     * Checks
     * - piVars one-dimensional filter
     * - piVars multi-dimensional filter
     * - backend preselected filter options
     * returns the filter options uids as values of an array or zero if no option has been selected.
     *
     * @param array $filter
     * @return array
     * @author Christian Bülter
     * @since 09.09.14
     */
    public function getSelectedFilterOptions($filter)
    {
        $selectedOptions = [];

        // Run through all the filter options and check if one of them has been selected.
        // The filter option can be selected in the frontend via piVars
        // or in the backend via flexform configuration ("preselected filters").
        foreach ($filter['options'] as $option) {
            $selected = false;

            if (
                isset($this->pObj->piVars['filter'][$filter['uid']])
                && $this->pObj->piVars['filter'][$filter['uid']] == $option['tag']
            ) {
                // one-dimensional piVar: filter option is set
                $selected = true;
            } elseif (is_array($this->pObj->piVars['filter'][$filter['uid']] ?? null)) {
                // multi-dimensional piVars
                if ($this->pObj->in_multiarray($option['tag'], $this->pObj->preselectedFilter)) {
                    $selected = true;
                    // add preselected filter to piVars
                    $this->pObj->piVars['filter'][$filter['uid']][$option['uid']] = $option['tag'];
                } else {
                    // already selected via piVars?
                    $selected = in_array($option['tag'], $this->pObj->piVars['filter'][$filter['uid']]);
                }
            } elseif (
                // No piVars for this filter are set or the length of the option is one character (dummy placeholder
                // for the routing configuration).
                !isset($this->pObj->piVars['filter'][$filter['uid']])
                || (
                    is_string($this->pObj->piVars['filter'][$filter['uid']])
                    && strlen($this->pObj->piVars['filter'][$filter['uid']]) === 1
                )
            ) {
                if ($this->pObj->in_multiarray($option['tag'], $this->pObj->preselectedFilter)) {
                    $selected = true;
                    // add preselected filter to piVars
                    $this->pObj->piVars['filter'][$filter['uid']] = [$option['uid'] => $option['tag']];
                }
            }

            if ($selected) {
                $selectedOptions[] = $option['uid'];
            }
        }

        return $selectedOptions;
    }

    /**
     * combines two string comma lists
     * @param string $list1
     * @param string $list2
     * @return string
     * @since 23.07.13
     * @author Christian Bülter
     */
    public function combineLists(string $list1 = '', string $list2 = ''): string
    {
        if (!empty($list1) && !empty($list2)) {
            $list1 .= ',';
        }
        $list1 .= $list2;
        $returnValue = StringUtility::uniqueList($list1);
        return $returnValue;
    }

    /**
     * Retrieves the filters associated with the current object.
     *
     * @return array The list of filters.
     */
    public function getFilters()
    {
        return $this->filters;
    }

    /**
     * Retrieves filter records from the database based on a list of filter UIDs.
     *
     * @param string $filterUids A comma-separated list of filter UIDs to fetch from the database.
     * @return array An array of filter records with options and language overlay applied, or an empty array if no filters are found.
     */
    public function getFiltersFromUidList($filterUids)
    {
        if (empty($filterUids)) {
            return [];
        }

        $table = 'tx_kesearch_filters';
        $where = 'pid in (' . $this->startingPoints . ')';
        $where .= ' AND find_in_set(uid, "' . $filterUids . '")';

        $queryBuilder = Db::getQueryBuilder('tx_kesearch_filters');
        $filterQuery = $queryBuilder
            ->select('*')
            ->from($table)
            ->where($where)
            ->executeQuery();

        // Retain original order from filters specified in configuration
        $filterRows = array_fill_keys(GeneralUtility::intExplode(',', $filterUids), null);

        while ($row = $filterQuery->fetchAssociative()) {
            $filterRows[$row['uid']] = $row;
        }

        // Remove filters that were in configuration but not found in database
        $filterRows = array_filter($filterRows);

        $filterRows = $this->languageOverlay($filterRows, $table);
        return $this->addOptionsToFilters($filterRows);
    }

    /**
     * Retrieves options from a list of UIDs.
     *
     * @param string $optionUids Comma-separated list of UIDs representing filter options.
     * @return array An array of filter options with language overlay applied.
     */
    public function getOptionsFromUidList($optionUids)
    {
        if (empty($optionUids)) {
            return [];
        }

        $table = 'tx_kesearch_filteroptions';
        $where = 'FIND_IN_SET(uid, "' . $optionUids . '")';
        $where .= ' AND pid in (' . $this->startingPoints . ')';

        $queryBuilder = Db::getQueryBuilder('tx_kesearch_filteroptions');

        $optionsQuery = $queryBuilder
            ->select('*')
            ->from($table)
            ->where($where)
            ->executeQuery();

        $optionsRows = [];
        while ($row = $optionsQuery->fetchAssociative()) {
            $optionsRows[$row['uid']] = $row;
        }

        // Apply language overlay
        $optionsOverlayed = $this->languageOverlay(
            $optionsRows,
            $table
        );

        // Reorder results to match the order of $optionUids
        // 1) Normalize the incoming list of UIDs
        $uidsInOrder = GeneralUtility::intExplode(',', (string)$optionUids, true);

        // 2) Ensure we have a map by UID even after overlay
        $optionsByUid = [];
        foreach ($optionsOverlayed as $key => $row) {
            $uid = isset($row['uid']) ? (int)$row['uid'] : (int)$key;
            $optionsByUid[$uid] = $row;
        }

        // 3) Build the ordered array according to the incoming UID list
        $ordered = [];
        foreach ($uidsInOrder as $uid) {
            if (isset($optionsByUid[$uid])) {
                $ordered[$uid] = $optionsByUid[$uid];
            }
        }

        return $ordered;
    }

    /**
     * Adds options to the provided filters by processing the 'options' field in each row.
     *
     * @param array $rows The array of filter rows, each having an 'options' field that may contain a UID list.
     * @return array The modified array of rows where the 'options' field is processed into structured data.
     */
    public function addOptionsToFilters(array $rows)
    {
        if (count($rows)) {
            foreach ($rows as $key => $row) {
                if (!empty($row['options'])) {
                    $rows[$key]['options'] = $this->getOptionsFromUidList($row['options']);
                } else {
                    $rows[$key]['options'] = [];
                }
            }
            return $rows;
        }
        return [];
    }

    /**
     * Applies language overlay to an array of database rows for a specific table.
     *
     * This function processes the given array of rows and applies the necessary language-based overlays
     * based on the current language context. It handles scenarios where no translated record exists
     * and applies the appropriate visibility settings. The function works with TYPO3's PageRepository
     * and language aspects to adjust the content accordingly.
     *
     * @param array $rows An array of database rows that need language overlay processing.
     * @param string $table The name of the database table associated with the rows.
     * @return array The processed array of rows with language overlays applied,
     *               or an empty array if no rows are available.
     */
    public function languageOverlay(array $rows, string $table): array
    {
        /** @var LanguageAspect $languageAspect */
        $languageAspect = GeneralUtility::makeInstance(Context::class)->getAspect('language');
        /** @var PageRepository $pageRepository */
        $pageRepository = GeneralUtility::makeInstance(PageRepository::class);

        $pageRecord = $this->pObj->request->getAttribute('frontend.page.information')->getPageRecord();

        // see https://github.com/teaminmedias-pluswerk/ke_search/issues/128
        $pageTranslationVisibility = new PageTranslationVisibility((int)$pageRecord['l18n_cfg']);

        if ($pageTranslationVisibility->shouldHideTranslationIfNoTranslatedRecordExists()) {
            $LanguageMode = 'hideNonTranslated';
        }
        if (count($rows)) {
            foreach ($rows as $key => $row) {
                if (is_array($row) && $languageAspect->getContentId() > 0) {
                    $row = $pageRepository->getLanguageOverlay(
                        $table,
                        $row,
                        $languageAspect
                    );

                    if (is_array($row)) {
                        if ($table == 'tx_kesearch_filters') {
                            $row['rendertype'] = $rows[$key]['rendertype'];
                        }
                        $rows[$key] = $row;
                    } else {
                        unset($rows[$key]);
                    }
                }
            }
            return $rows;
        }
        return [];
    }

    /**
     * Checks if a given tag matches the records in the search result.
     *
     * @param string $tag The tag to check for in the search result records.
     * @return bool True if the tag is found in the records, false otherwise.
     */
    public function checkIfTagMatchesRecords(string $tag): bool
    {
        // If the tag list is not defined yet, fetch it from the result list, otherwise use the cached tag list.
        if ($this->tagsInSearchResult === false) {
            if ($this->pObj->tagsInSearchResult === false) {
                $this->tagsInSearchResult = $this->pObj->tagsInSearchResult = $this->db->getTagsFromSearchResult();
            } else {
                $this->tagsInSearchResult = $this->pObj->tagsInSearchResult;
            }
        }

        return array_key_exists($tag, $this->tagsInSearchResult);
    }

    /**
     * returns the tag char: a character which wraps tags in the database
     *
     * @return string
     */
    public function getTagChar()
    {
        return $this->tagChar;
    }

    /**
    * returns the starting points IDs
    *
    * @return string
    */
    public function getStartingPoints()
    {
        return $this->startingPoints;
    }
}
