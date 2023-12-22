<?php

namespace Tpwd\KeSearch\Lib;

use Tpwd\KeSearch\Plugins\PluginBase;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/***************************************************************
 *  Copyright notice
 *  (c) 2010 Stefan Froemken
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
 * Helper class for ke_search Plugin
 *
 * @author    Stefan Froemken
 */
class PluginBaseHelper
{
    public PluginBase $pObj;
    private ContentObjectRenderer $cObj;

    public function __construct(PluginBase $pObj)
    {
        $this->pObj = $pObj;
        $this->cObj = $pObj->getContentObjectRenderer();
    }

    public function getStartingPoint(): string
    {
        $startingpoint = [];

        // if loadFlexformsFromOtherCE is set
        // try to get startingPoint of given page
        // @extensionScannerIgnoreLine
        if ($uid = (int)($this->pObj->conf['loadFlexformsFromOtherCE'] ?? 0)) {
            $queryBuilder = Db::getQueryBuilder('tt_content');
            $queryBuilder->getRestrictions()->removeAll();
            $pageResult = $queryBuilder
                ->select('pages', 'recursive')
                ->from('tt_content')
                ->where(
                    $queryBuilder->expr()->eq(
                        'uid',
                        $queryBuilder->createNamedParameter($uid, \PDO::PARAM_INT)
                    )
                )
                ->executeQuery()
                ->fetchAssociative();
            if (is_array($pageResult) && count($pageResult)) {
                $startingpoint['pages'] = $pageResult['pages'];
                $startingpoint['recursive'] = $pageResult['recursive'];
            }
        } else {
            // if loadFlexformsFromOtherCE is NOT set
            // get startingPoints of current page
            $startingpoint['pages'] = $this->cObj->data['pages'] ?? false;
            $startingpoint['recursive'] = $this->cObj->data['recursive'] ?? false;
        }

        // allow to override startingpoint with typoscript like this
        // plugin.tx_kesearch_pi1.overrideStartingPoint = 123
        // plugin.tx_kesearch_pi1.overrideStartingPointRecursive = 1
        if ($this->pObj->conf['overrideStartingPoint'] ?? false) {
            $startingpoint['pages'] = $this->pObj->conf['overrideStartingPoint'];
            $startingpoint['recursive'] = $this->pObj->conf['overrideStartingPointRecursive'];
        }

        return $this->pObj->pi_getPidList($startingpoint['pages'], $startingpoint['recursive']);
    }

    /**
     * Get the first page of starting points
     *
     * @param string $pages comma seperated list of page-uids
     * @return int first page uid
     */
    public function getFirstStartingPoint(string $pages = ''): int
    {
        $pageArray = explode(',', $pages);
        return (int)($pageArray[0]);
    }

    /**
     * function cleanPiVars
     * cleans piVars
     * sword is not cleaned at this point.
     * This is done when outputting and querying the database.
     * htmlspecialchars(...) and / or intval(...)
     *
     * @param array $piVars array containing all piVars
     * @param string $additionalAllowedPiVars comma-separated list
     * @return array
     */
    public function cleanPiVars(array $piVars, string $additionalAllowedPiVars = ''): array
    {
        foreach ($piVars as $key => $value) {
            if (in_array($key, SearchHelper::PI_VARS_STRING)) {
                if (!is_string(($value))) {
                    $piVars[$key] = '';
                }
            }
        }

        // process further cleaning regarding to param type
        foreach ($piVars as $key => $value) {
            switch ($key) {
                // integer - default 1
                case 'page':
                    $piVars[$key] = (int)$value;
                    // set to "1" if no value set
                    if (!$piVars[$key]) {
                        $piVars[$key] = 1;
                    }
                    break;

                    // integer
                case 'resetFilters':
                    $piVars[$key] = (int)$value;
                    break;

                    // array of strings. Defined in the TYPO3 backend
                    // and posted as piVar. Should not contain any special
                    // chars (<>"), but just to make sure we remove them here.
                case 'filter':
                    if (is_array($piVars[$key])) {
                        foreach ($piVars[$key] as $filterId => $filterValue) {
                            if (is_array($piVars[$key][$filterId])) {
                                foreach ($piVars[$key][$filterId] as $subKey => $value) {
                                    $piVars[$key][$filterId][$subKey] = htmlspecialchars($value, ENT_QUOTES);
                                }
                            } else {
                                if ($piVars[$key][$filterId] != null) {
                                    $piVars[$key][$filterId] = htmlspecialchars($filterValue, ENT_QUOTES);
                                }
                            }
                        }
                    }
                    break;

                    // string, no further XSS cleaning here
                    // cleaning is done on output
                case 'sword':
                    $piVars[$key] = trim($piVars[$key]);
                    break;

                    // only characters
                case 'sortByField':
                    $piVars[$key] = preg_replace('/[^a-zA-Z0-9]/', '', $piVars[$key]);
                    break;

                    // "asc" or "desc"
                case 'sortByDir':
                    if ($piVars[$key] != 'asc' && $piVars[$key] != 'desc') {
                        $piVars[$key] = 'asc';
                    }
                    break;

                    // remove not allowed piVars
                default:
                    if (!in_array($key, SearchHelper::getAllowedPiVars($additionalAllowedPiVars))) {
                        unset($piVars[$key]);
                    }
                    break;
            }
        }

        // return cleaned piVars values
        return $piVars;
    }
}
