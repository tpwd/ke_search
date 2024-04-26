<?php

/** @noinspection PhpUndefinedClassInspection */
/** @noinspection PhpUndefinedNamespaceInspection */
/** @noinspection PhpPropertyOnlyWrittenInspection */

namespace Tpwd\KeSearch\Plugins;

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
use Psr\Http\Message\ServerRequestInterface;
use Tpwd\KeSearch\Domain\Repository\FileMetaDataRepository;
use Tpwd\KeSearch\Domain\Repository\FileReferenceRepository;
use Tpwd\KeSearch\Domain\Repository\GenericRepository;
use Tpwd\KeSearch\Lib\Db;
use Tpwd\KeSearch\Lib\Filters;
use Tpwd\KeSearch\Lib\PluginBaseHelper;
use Tpwd\KeSearch\Lib\SearchHelper;
use Tpwd\KeSearch\Lib\Searchphrase;
use Tpwd\KeSearch\Lib\Searchresult;
use Tpwd\KeSearch\Lib\Sorting;
use Tpwd\KeSearch\Utility\RequestUtility;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\LanguageAspect;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Resource\FilePathSanitizer;

/**
 * Parent class for plugins ResultlistPlugin and SearchboxPlugin
 *
 * @author    Andreas Kiefer
 * @author    Stefan Froemken
 * @author    Christian Bülter
 */
class PluginBase extends AbstractPlugin
{
    protected ?ServerRequestInterface $request = null;
    public Db $db;
    public PluginBaseHelper $div;
    public Filters $filters;

    public string $prefixId = 'tx_kesearch_pi1';
    public string $extKey = 'ke_search';
    public array $piVars = [];

    // cleaned searchword (karl-heinz => karl heinz)
    public string $sword = '';

    // searchwords as array
    public array $swords;

    // searchphrase for boolean mode (+karl* +heinz*)
    public string $wordsAgainst = '';

    // tagsphrase for boolean mode (+#category_213# +#city_42#)
    public array $tagsAgainst = [];

    // searchphrase for score/non boolean mode (karl heinz)
    public string $scoreAgainst = '';

    // true if no searchparams given; otherwise false
    public bool $isEmptySearch = true;

    // comma seperated list of startingPoints
    public string $startingPoints = '';

    // first entry in list of startingpoints
    public int $firstStartingPoint = 0;

    // Extension-Configuration
    public array $extConf = [];

    // Extension-Configuration of ke_search_premium if installed
    public array $extConfPremium = [];

    // count search results
    public int $numberOfResults = 0;

    /**
     * contains all tags of current search result, false if not initialized yet
     * @var bool|array
     */
    public $tagsInSearchResult = false;

    // preselected filters by flexform
    public array $preselectedFilter = [];

    // contains a boolean value which represents if there are too short words in the search string
    public bool $hasTooShortWords = false;

    // contains all the variables passed to the fluid template
    public array $fluidTemplateVariables = [];

    // Frontend language ID
    protected int $languageId;

    // Helper variable to pass the value to a hook
    private int $currentRowNumber;

    /**
     * Initializes flexform, conf vars and some more
     * Initializes $this->piVars if $this->prefixId is set to any value
     */
    public function init(ServerRequestInterface $request)
    {
        $this->setRequest($request);
        /** @var Context $context */
        $context = GeneralUtility::makeInstance(Context::class);
        /** @var LanguageAspect $languageAspect */
        $languageAspect = $context->getAspect('language');
        $this->languageId = $languageAspect->getId();

        // Set piVars
        if ($this->prefixId) {
            $this->piVars = RequestUtility::getQueryParam($this->request, $this->prefixId) ?? [];
        }

        // get some helper functions
        $this->div = GeneralUtility::makeInstance(PluginBaseHelper::class, $this);

        // set start of query timer
        if (!($GLOBALS['TSFE']->register['ke_search_queryStartTime'] ?? false)) {
            $GLOBALS['TSFE']->register['ke_search_queryStartTime'] = round(microtime(true) * 1000);
        }

        // Use alternative search word parameter (e.g. "query=") in URL but map to tx_kesearch_pi1[sword]=
        $searchWordParameter = SearchHelper::getSearchWordParameter();
        if (!isset($this->piVars['sword']) && RequestUtility::getQueryParam($this->request, $searchWordParameter)) {
            $this->piVars['sword'] = RequestUtility::getQueryParam($this->request, $searchWordParameter);
        }

        // get the configuration of the current plugin
        $flexFormConfiguration = $this->getFlexFormConfiguration();

        // In the list plugin we need to fetch the FlexForm from the search box plugin, because all the configuration
        // is done there. The search box plugin to fetch the configuration from is defined in the the FlexForm setting
        // "loadFlexformsFromOtherCE".
        $loadFlexformsFromOtherCE = false;
        if (!empty($flexFormConfiguration['loadFlexformsFromOtherCE'])) {
            $loadFlexformsFromOtherCE = $flexFormConfiguration['loadFlexformsFromOtherCE'];
        }

        // When TypoScript is used to spawn a COA of tx_kesearch_pi1 plugin, the above will be empty (as no plugin
        // container exists). For this specific case, we use the ->conf[] array and parse its setting.
        if (empty($loadFlexformsFromOtherCE) && !empty($this->conf['loadFlexformsFromOtherCE'])) {
            $loadFlexformsFromOtherCE = $this->conf['loadFlexformsFromOtherCE'];
        }

        if (!empty($loadFlexformsFromOtherCE)) {
            $currentFlexFormConfiguration = $flexFormConfiguration;
            $contentElement = $this->pi_getRecord('tt_content', (int)($loadFlexformsFromOtherCE));
            if (is_int($contentElement) && $contentElement == 0) {
                throw new Exception('Content element with search configuration is not set or not accessible. Maybe hidden or deleted?');
            }
            $this->cObj->data['pi_flexform'] = $contentElement['pi_flexform'];
            $flexFormConfiguration = array_merge($currentFlexFormConfiguration, $this->getFlexFormConfiguration());

            // After merging the FlexForm configurations of the two content elements we need to make sure the
            // value for "loadFlexformsFromOtherCE" is set back to the original value because it may happen that
            // in the search box plugin that FlexForm value is an empty value which would override the original value.
            // That can happen if the content element was first set to "result list" and then changed to "search box".
            // Then we would have an empty value for "loadFlexformsFromOtherCE".
            $this->conf['loadFlexformsFromOtherCE'] = $loadFlexformsFromOtherCE;
        }

        // make settings from FlexForm available in general configuration ($this->conf)
        $this->moveFlexFormDataToConf($flexFormConfiguration);

        // explode flattened piVars to multi-dimensional array and clean them
        $additionalAllowedPiVars = $this->conf['additionalAllowedPiVars'] ?? '';
        $this->piVars = SearchHelper::explodePiVars($this->piVars, $additionalAllowedPiVars);
        $this->piVars = $this->div->cleanPiVars($this->piVars, $additionalAllowedPiVars);

        // hook: modifyFlexFormData
        if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['modifyFlexFormData'] ?? null)) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['modifyFlexFormData'] as $_classRef) {
                $_procObj = GeneralUtility::makeInstance($_classRef);
                $_procObj->modifyFlexFormData($this->conf, $this->cObj, $this->piVars);
            }
        }

        // get preselected filter from rootline
        $this->getFilterPreselect();

        // add stdWrap properties to each config value (not to arrays)
        foreach ($this->conf as $key => $value) {
            if (!is_array($this->conf[$key] ?? null)) {
                $this->conf[$key] = $this->cObj->stdWrap($value, $this->conf[$key . '.'] ?? []);
            }
        }

        // set some default values (this part has to be after stdWrap!!!)
        if (!($this->conf['resultPage'] ?? false)) {
            // @extensionScannerIgnoreLine
            $this->conf['resultPage'] = $GLOBALS['TSFE']->id;
        }
        if (!isset($this->piVars['page'])) {
            $this->piVars['page'] = 1;
        }
        if (!empty($this->conf['additionalPathForTypeIcons'])) {
            $this->conf['additionalPathForTypeIcons'] = rtrim($this->conf['additionalPathForTypeIcons'], '/') . '/';
        }

        // prepare database object
        $this->db = GeneralUtility::makeInstance(Db::class);
        if (!isset($this->db->pObj)) {
            $this->db->setPluginbase($this);
        }

        // set startingPoints
        $this->startingPoints = $this->div->getStartingPoint();

        // get filter class
        $this->filters = GeneralUtility::makeInstance(Filters::class);

        // get extension configuration array
        $this->extConf = SearchHelper::getExtConf();
        $this->extConfPremium = SearchHelper::getExtConfPremium();

        // initialize filters
        $this->filters->initialize($this);

        // get first startingpoint
        $this->firstStartingPoint = $this->div->getFirstStartingPoint($this->startingPoints);

        // build words searchphrase
        /** @var Searchphrase $searchPhrase */
        $searchPhrase = GeneralUtility::makeInstance(Searchphrase::class);
        $searchPhrase->initialize($this);
        $searchWordInformation = $searchPhrase->buildSearchPhrase();

        // Hook: modifySearchWords
        if (isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['modifySearchWords'])) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['modifySearchWords'] as $classRef) {
                $hookObj = GeneralUtility::makeInstance($classRef);
                if (method_exists($hookObj, 'modifySearchWords')) {
                    $hookObj->modifySearchWords($searchWordInformation, $this);
                }
            }
        }

        // set searchword and tag information
        $this->sword = $searchWordInformation['sword'];
        $this->swords = $searchWordInformation['swords'];
        $this->wordsAgainst = $searchWordInformation['wordsAgainst'];
        $this->scoreAgainst = $searchWordInformation['scoreAgainst'];
        $this->tagsAgainst = $searchWordInformation['tagsAgainst'];

        $this->isEmptySearch = $this->isEmptySearch();

        // Since sorting for "relevance" in most cases ist the most useful option and
        // this sorting option is not available until a searchword is given, make it
        // the default sorting after a searchword has been given.
        // Set default sorting to "relevance" if the following conditions are true:
        // * sorting by user is allowed
        // * sorting for "relevance" is allowed (internal: "score")
        // * user did not select his own sorting yet
        // * a searchword is given
        $isInList = GeneralUtility::inList($this->conf['sortByVisitor'] ?? '', 'score');
        if (($this->conf['showSortInFrontend'] ?? false) && $isInList && !($this->piVars['sortByField'] ?? false) && $this->sword) {
            $this->piVars['sortByField'] = 'score';
            $this->piVars['sortByDir'] = 'desc';
        }

        // after the searchword is removed, sorting for "score" is not possible
        // anymore. So remove this sorting here and put it back to default.
        if (!$this->sword && ($this->piVars['sortByField'] ?? '') == 'score') {
            unset($this->piVars['sortByField']);
            unset($this->piVars['sortByDir']);
        }

        // perform search at this point already if we need to calculate what
        // filters to display.
        if (isset($this->conf['checkFilterCondition']) && $this->conf['checkFilterCondition'] != 'none' && !$this->allowEmptySearch()) {
            $this->db->getSearchResults();
        }

        // add cssTag to header if set
        if (!empty($this->conf['cssFile'])) {
            $filePathSanitizer = GeneralUtility::makeInstance(FilePathSanitizer::class);
            $cssFile = $filePathSanitizer->sanitize($this->conf['cssFile'], true);
            if (!empty($cssFile)) {
                /** @var PageRenderer $pageRenderer */
                $pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
                $pageRenderer->addCssFile($cssFile);
            }
        }
    }

    /**
     * Move all FlexForm data of current record to conf array ($this->conf)
     *
     * @param array $flexFormConfiguration
     */
    public function moveFlexFormDataToConf(array $flexFormConfiguration)
    {
        if (!empty($flexFormConfiguration)) {
            foreach ($flexFormConfiguration as $key => $value) {
                if (($this->conf[$key] ?? '') != $value && !empty($value)) {
                    $this->conf[$key] = $value;
                }
            }
        }
    }

    /**
     * Returns the FlexForm configuration of the plugin as array.
     *
     * @return array
     */
    public function getFlexFormConfiguration(): array
    {
        $flexFormConfiguration = [];
        if (isset($this->cObj->data['pi_flexform'])) {
            $this->pi_initPIflexForm();
            $piFlexForm = $this->cObj->data['pi_flexform'];
            if (is_array($piFlexForm['data'])) {
                foreach ($piFlexForm['data'] as $sheetKey => $sheet) {
                    foreach ($sheet as $lang) {
                        foreach ($lang as $key => $value) {
                            $flexFormConfiguration[$key] = $this->fetchConfigurationValue($key, $sheetKey);
                        }
                    }
                }
            }
        }
        return $flexFormConfiguration;
    }

    /**
     * creates the searchbox
     * fills fluid variables for the fluid template to $this->fluidTemplateVariables
     */
    public function getSearchboxContent()
    {
        // set page = 1 for every new search
        $pageValue = 1;
        $this->fluidTemplateVariables['page'] = $pageValue;

        // searchword input value
        $searchString = $this->piVars['sword'] ?? '';

        $searchboxDefaultValue = LocalizationUtility::translate(
            'LLL:EXT:ke_search/Resources/Private/Language/locallang_searchbox.xlf:searchbox_default_value',
            'KeSearch'
        );

        if (!empty($searchString) && $searchString != $searchboxDefaultValue) {
            $swordValue = $searchString;
        } else {
            $swordValue = '';
        }

        $this->fluidTemplateVariables['searchword'] = htmlspecialchars($swordValue);
        $this->fluidTemplateVariables['searchwordDefault'] = $searchboxDefaultValue;
        $this->fluidTemplateVariables['sortByField'] = $this->piVars['sortByField'] ?? '';
        $this->fluidTemplateVariables['sortByDir'] = $this->piVars['sortByDir'] ?? '';

        // get filters
        $this->renderFilters();

        // set form action pid
        $this->fluidTemplateVariables['targetpage'] = $this->conf['resultPage'];
        $this->fluidTemplateVariables['targetPageUrl']
            = GeneralUtility::makeInstance(ContentObjectRenderer::class)
            ->typoLink_URL(['parameter' => $this->conf['resultPage']]);

        // set form action
        $siteUrl = GeneralUtility::getIndpEnv('TYPO3_SITE_URL');
        $lParam = RequestUtility::getQueryParam($this->request, 'L');
        $mpParam = RequestUtility::getQueryParam($this->request, 'MP');
        $typeParam = RequestUtility::getQueryParam($this->request, 'type');
        $actionUrl = $siteUrl . 'index.php';
        $this->fluidTemplateVariables['actionUrl'] = $actionUrl;

        // language parameter
        if (isset($lParam)) {
            $hiddenFieldValue = (int)$lParam;
            $this->fluidTemplateVariables['lparam'] = $hiddenFieldValue;
        }

        // mountpoint parameter
        if (isset($mpParam)) {
            // the only allowed characters in the MP parameter are digits and , and -
            $hiddenFieldValue = preg_replace('/[^0-9,-]/', '', $mpParam);
            $this->fluidTemplateVariables['mpparam'] = $hiddenFieldValue;
        }

        // type param
        if ($typeParam) {
            $hiddenFieldValue = (int)$typeParam;
            $this->fluidTemplateVariables['typeparam'] = $hiddenFieldValue;
        }

        // set reset link
        $this->fluidTemplateVariables['resetUrl'] = SearchHelper::searchLink($this->conf['resultPage']);

        // set isEmptySearch flag
        $this->fluidTemplateVariables['isEmptySearch'] = $this->isEmptySearch;
    }

    /**
     * loop through all available filters and compile the values for the fluid template rendering
     */
    public function renderFilters()
    {
        foreach ($this->filters->getFilters() as $filter) {
            // if the current filter is a "hidden filter", skip
            // rendering of this filter. The filter is only used
            // to add preselected filter options to the query and
            // must not be rendered.
            $isInList = GeneralUtility::inList($this->conf['hiddenfilters'] ?? '', $filter['uid']);

            if ($isInList) {
                continue;
            }

            // get filter options which should be displayed
            $options = $this->findFilterOptionsToDisplay($filter);

            // alphabetical sorting of filter options
            if ($filter['alphabeticalsorting'] == 1) {
                $this->sortArrayByColumn($options, 'title');
            }

            // hook for modifying filter options
            if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['modifyFilterOptionsArray'] ?? null)) {
                foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['modifyFilterOptionsArray'] as
                         $_classRef) {
                    $_procObj = GeneralUtility::makeInstance($_classRef);
                    $options = $_procObj->modifyFilterOptionsArray($filter['uid'], $options, $this);
                }
            }

            // build link to reset this filter while keeping the others
            $resetLink = SearchHelper::searchLink($this->conf['resultPage'], $this->piVars, [$filter['uid']]);

            // set values for fluid template
            $filterData = $filter;
            $filterData['name'] = 'tx_kesearch_pi1[filter_' . $filter['uid'] . ']';
            $filterData['id'] = 'filter_' . $filter['uid'];
            $filterData['options'] = $options;
            $filterData['checkboxOptions'] = $this->compileCheckboxOptions($filter, $options);
            $filterData['optionCount'] = is_array($options) ? count($options) : 0;
            $filterData['resetLink'] = $resetLink;
            if ($filter['rendertype'] == 'dateRange') {
                $filterData['start'] = $this->piVars['filter'][$filter['uid']]['start'] ?? '';
                $filterData['end'] = $this->piVars['filter'][$filter['uid']]['end'] ?? '';
            }

            // use custom code for filter rendering
            // set $filterData['rendertype'] = 'custom'
            // and $filterData['rawHtmlContent'] to your pre-rendered filter code
            // hook for custom filter renderer
            if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['customFilterRenderer'] ?? null)) {
                foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['customFilterRenderer'] as
                         $_classRef) {
                    $_procObj = GeneralUtility::makeInstance($_classRef);
                    $_procObj->customFilterRenderer($filter['uid'], $options, $this, $filterData);
                }
            }

            // add values to fluid template
            $this->fluidTemplateVariables['filters'][] = $filterData;
        }
    }

    /**
     * compiles a list of checkbox records
     * @param array $filter filter record for which we need the checkboxes
     * @param array $options contains all options which are found in the search result
     * @return array list of checkboxes records
     */
    public function compileCheckboxOptions(array $filter, array $options): array
    {
        $allOptionsOfCurrentFilter = $filter['options'];

        // alphabetical sorting of filter options
        if ($filter['alphabeticalsorting'] == 1) {
            $this->sortArrayByColumn($allOptionsOfCurrentFilter, 'title');
        }

        // loop through options
        $checkboxOptions = [];
        if (is_array($allOptionsOfCurrentFilter)) {
            foreach ($allOptionsOfCurrentFilter as $key => $data) {
                $data['key'] = 'tx_kesearch_pi1[filter_' . $filter['uid'] . '_' . $key . ']';

                // check if current option (of searchresults) is in array of all possible options
                $isOptionInOptionArray = false;
                if (is_array($options)) {
                    foreach ($options as $optionInResultList) {
                        if ($optionInResultList['value'] == $data['tag']) {
                            $isOptionInOptionArray = true;
                            $data['results'] = $optionInResultList['results'] ?? 0;
                            break;
                        }
                    }
                }

                // if option is in optionArray, we have to mark the checkboxes
                if ($isOptionInOptionArray) {
                    // if user has selected a checkbox it must be selected on the resultpage, too.
                    // options which have been preselected in the backend are
                    // already in $this->piVars['filter'][$filter['uid]]
                    if ($this->piVars['filter'][$filter['uid']][$key] ?? false) {
                        $data['selected'] = 1;
                    }

                    // mark all checkboxes if that config options is set and no search string was given and there
                    // are no preselected filters given for that filter
                    if ($this->isEmptySearch
                        && $filter['markAllCheckboxes']
                        && empty($this->preselectedFilter[$filter['uid']])
                    ) {
                        $data['selected'] = 1;
                    }
                } else { // if an option was not found in the search results
                    $data['disabled'] = 1;
                }

                $data['id'] = 'filter_' . $filter['uid'] . '_' . $key;
                $checkboxOptions[] = $data;
            }
        }

        // modify filter options by hook
        if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['modifyFilterOptions'] ?? null)) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['modifyFilterOptions'] as $_classRef) {
                $_procObj = GeneralUtility::makeInstance($_classRef);
                $_procObj->modifyFilterOptions($filter, $checkboxOptions, $this);
            }
        }

        return $checkboxOptions;
    }

    /**
     * find out which filter options should be displayed for the given filter
     * check filter options availability and preselection status
     * @param array $filter
     * @return array
     * @author Christian Bülter
     * @since 09.09.14
     */
    public function findFilterOptionsToDisplay(array $filter): array
    {
        $optionsToDisplay = [];

        foreach ($filter['options'] as $option) {
            // build link which selects this option for this filter and keeps all the other filters
            $localPiVars = $this->piVars;
            $localPiVars['filter'][$filter['uid']] = $option['tag'];
            // We need to unset the page in order to jump to the first page when a filter is selected.
            // https://github.com/tpwd/ke_search/issues/24
            unset($localPiVars['page']);
            $optionLink = SearchHelper::searchLink($this->conf['resultPage'], $localPiVars);

            // Should we check if the filter option is available in the current search result?
            // multi --> Check for each filter option if it has results - display it only if it has results!
            // none --> Just show all filter options, no matter wether they have results or not.
            if ($this->conf['checkFilterCondition'] != 'none') {
                // Once one filter option has been selected, don't display the
                // others anymore since this leads to a strange behaviour (options are
                // only displayed if they have BOTH tags: the selected and the other filter option.
                if (
                    (!count($filter['selectedOptions']) || in_array($option['uid'], $filter['selectedOptions']))
                    && $this->filters->checkIfTagMatchesRecords($option['tag'])
                ) {
                    $optionsToDisplay[$option['uid']] = [
                        'title' => $option['title'],
                        'value' => $option['tag'],
                        'results' => $this->tagsInSearchResult[$option['tag']],
                        'selected' =>
                            is_array($filter['selectedOptions'])
                            && !empty($filter['selectedOptions'])
                            && in_array($option['uid'], $filter['selectedOptions']),
                        'link' => $optionLink,
                    ];
                }
            } else {
                // do not process any checks; show all filter options
                $optionsToDisplay[$option['uid']] = [
                    'title' => $option['title'],
                    'value' => $option['tag'],
                    'selected' =>
                        is_array($filter['selectedOptions'])
                        && !empty($filter['selectedOptions'])
                        && in_array($option['uid'], $filter['selectedOptions']),
                    'link' => $optionLink,
                ];

                // If no filter option has been selected yet, we can show the number of results per filter option.
                // After a filter option has been selected this does not make sense anymore because the number of
                // number of results per filter option is calculated from the current result set, not from the
                // full index but since 'checkFilterConditon' is set to 'none' at this point all filter options are shown.
                if ($filter['shownumberofresults'] && !count($filter['selectedOptions'])) {
                    if ($this->filters->checkIfTagMatchesRecords($option['tag'])) {
                        $optionsToDisplay[$option['uid']]['results'] = $this->tagsInSearchResult[$option['tag']];
                    } else {
                        $optionsToDisplay[$option['uid']]['results'] = 0;
                    }
                }
            }
        }

        return $optionsToDisplay;
    }

    /**
     * renders brackets around the number of results, returns an empty
     * string if there are no results or if an option for this filter already
     * has been selected.
     * @param int $numberOfResults
     * @param array $filter
     * @return string
     */
    public function renderNumberOfResultsString(int $numberOfResults, array $filter): string
    {
        if ($filter['shownumberofresults'] && !count($filter['selectedOptions']) && $numberOfResults) {
            $returnValue = ' (' . $numberOfResults . ')';
        } else {
            $returnValue = '';
        }
        return $returnValue;
    }

    /**
     * set the text for "no results"
     */
    public function setNoResultsText()
    {
        // no results found
        if ($this->conf['showNoResultsText'] ?? false) {
            // use individual text set in flexform
            $noResultsText = $this->pi_RTEcssText($this->conf['noResultsText'] ?? '');
        } else {
            // use general text
            $noResultsText = $this->pi_getLL('no_results_found');
        }

        // hook to implement your own idea of a no result message
        if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['noResultsHandler'] ?? null)) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['noResultsHandler'] as $_classRef) {
                $_procObj = GeneralUtility::makeInstance($_classRef);
                $_procObj->noResultsHandler($noResultsText, $this);
            }
        }

        // fill the fluid template marker
        $this->fluidTemplateVariables['noResultsText'] = $noResultsText;
    }

    /**
     * creates the search result list
     * 1. does the actual searching (fetches the results to $rows)
     * 2. fills fluid variables for fluid templates to $this->fluidTemplateVariables
     */
    public function getSearchResults()
    {
        /** @var GenericRepository $genericRepository */
        $genericRepository = GeneralUtility::makeInstance(GenericRepository::class);
        /** @var FileMetaDataRepository $fileMetaDataRepository */
        $fileMetaDataRepository = GeneralUtility::makeInstance(FileMetaDataRepository::class);

        // set switch for too short words
        $this->fluidTemplateVariables['wordsTooShort'] = $this->hasTooShortWords ? 1 : 0;

        if ($this->isEmptySearch() && !$this->allowEmptySearch()) {
            return;
        }

        // get filters
        if (isset($this->conf['includeFilters']) && (int)($this->conf['includeFilters']) == 1) {
            $this->renderFilters();
        }

        // fetch the search results
        $limit = $this->db->getLimit();
        $rows = $this->db->getSearchResults();
        $this->fluidTemplateVariables['errors'] = $this->db->getErrors();

        // set number of results
        $this->numberOfResults = $this->db->getAmountOfSearchResults();

        // count search phrase in ke_search statistic tables
        if ($this->conf['countSearchPhrases'] ?? '') {
            $this->countSearchPhrase($this->sword, $this->swords, $this->numberOfResults, $this->tagsAgainst);
        }

        // render "no results" text and stop here
        if ($this->numberOfResults == 0) {
            $this->setNoResultsText();
        }

        // init counter and loop through the search results
        $resultCount = 1;
        $resultRowRenderer = GeneralUtility::makeInstance(Searchresult::class);
        $resultRowRenderer->setPluginConfiguration($this->conf);
        $resultRowRenderer->setSwords($this->swords);

        $this->fluidTemplateVariables['resultrows'] = [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $resultRowRenderer->setRow($row);

                $tempMarkerArray = [
                    'orig_uid' => $row['orig_uid'],
                    'orig_pid' => $row['orig_pid'],
                    'orig_row' => $genericRepository->findByUidAndType($row['orig_uid'], $row['type']),
                    'title_text' => $row['title'],
                    'content_text' => $row['content'],
                    'title' => $resultRowRenderer->getTitle(),
                    'teaser' => $resultRowRenderer->getTeaser(),
                ];

                if (substr($row['type'], 0, 4) == 'file' && !empty($row['orig_uid'])) {
                    $tempMarkerArray['metadata'] = $fileMetaDataRepository->findByFileUidAndLanguageUid(
                        $row['orig_uid'],
                        $this->languageId
                    );
                }

                // hook for additional markers in result row
                if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['additionalResultMarker'] ?? null)) {
                    // make curent row number available to hook
                    $this->currentRowNumber = $resultCount;
                    foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['additionalResultMarker'] as $_classRef) {
                        $_procObj = GeneralUtility::makeInstance($_classRef);
                        $_procObj->additionalResultMarker($tempMarkerArray, $row, $this);
                    }
                    unset($this->currentRowNumber);
                }

                // add type marker
                // for file results just use the "file" type, not the file extension (eg. "file:pdf")
                list($type) = explode(':', $row['type']);
                $tempMarkerArray['type'] = str_replace(' ', '_', $type);

                // use the markers array as a base for the fluid template values
                $resultrowTemplateValues = $tempMarkerArray;

                // set result url
                $resultUrl = $resultRowRenderer->getResultUrl($this->conf['renderResultUrlAsLink'] ?? false);
                $resultrowTemplateValues['url'] = $resultUrl;

                // set result numeration
                $resultNumber = $resultCount
                    + ($this->piVars['page'] * $this->conf['resultsPerPage'])
                    - $this->conf['resultsPerPage'];
                $resultrowTemplateValues['number'] = $resultNumber;

                // set date (formatted and raw as a timestamp)
                $resultDate = date($GLOBALS['TYPO3_CONF_VARS']['SYS']['ddmmyy'], $row['sortdate']);
                $resultrowTemplateValues['date'] = $resultDate;
                $resultrowTemplateValues['date_timestamp'] = $row['sortdate'];

                // show tags?
                $tags = $row['tags'];
                $tags = str_replace('#', ' ', $tags);
                $resultrowTemplateValues['tags'] = $tags;

                // set preview image and/or type icons
                // for files we have the corresponding entry in sys_file as "orig_uid" available (not sys_file_reference)
                // for pages and news we have to fetch the file reference uid
                if ($type == 'file') {
                    $fileExtension = '';
                    if ($this->conf['showFilePreview'] ?? '') {
                        // SearchHelper::getFile will return af FILE object if it is a FAL file,
                        // otherwise it's a plain path to a file
                        $file = SearchHelper::getFile($row['orig_uid']);
                        if ($file) {
                            // FAL file
                            $resultrowTemplateValues['filePreviewId'] = $row['orig_uid'];
                            $fileExtension = $file->getExtension();
                        } else {
                            // no FAL file or FAL file does not exist
                            if (file_exists($row['directory'] . $row['title'])) {
                                $resultrowTemplateValues['filePreviewId'] = $row['directory'] . $row['title'];
                                $fileExtension = pathinfo($row['title'], PATHINFO_EXTENSION);
                            }
                        }
                    }
                    $filePreviewPossible = in_array(
                        $fileExtension,
                        GeneralUtility::trimExplode(
                            ',',
                            $GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext'],
                            true
                        )
                    );
                    if (!$filePreviewPossible) {
                        $resultrowTemplateValues['filePreviewId'] = 0;
                    }
                    $resultrowTemplateValues['treatIdAsReference'] = 0;
                } else {
                    $resultrowTemplateValues['filePreviewId'] = $this->getFileReference($row);
                    $resultrowTemplateValues['treatIdAsReference'] = 1;
                }

                // get the icon for the current record type
                $resultrowTemplateValues['typeIconPath'] = $this->getTypeIconPath($row['type']);

                // set end date for cal events
                if ($type == 'cal') {
                    $resultrowTemplateValues['cal'] = $this->getCalEventEnddate($row['orig_uid']);
                }

                // add result row to the variables array
                $this->fluidTemplateVariables['resultrows'][] = $resultrowTemplateValues;

                // increase result counter
                $resultCount++;
            }
        }
    }

    /**
     * get file reference for image rendering in fluid
     *
     * @param $row
     * @return int uid of preview image file reference
     * @author Andreas Kiefer
     */
    public function getFileReference($row): int
    {
        list($type) = explode(':', $row['type']);
        switch ($type) {
            case 'page':
                if ($this->conf['showPageImages'] ?? false) {
                    // first check if "tx_kesearch_resultimage" is set
                    $result = $this->getFirstFalRelationUid(
                        'pages',
                        'tx_kesearch_resultimage',
                        $row['orig_uid']
                    );

                    // fallback to standard "media" field
                    if (empty($result)) {
                        $result = $this->getFirstFalRelationUid(
                            'pages',
                            'media',
                            $row['orig_uid']
                        );
                    }
                    return $result;
                }
                break;

            case 'tt_news':
                if ($this->conf['showNewsImages'] ?? false) {
                    return $this->getFirstFalRelationUid(
                        'tt_news',
                        'image',
                        $row['orig_uid']
                    );
                }
                break;

            case 'news':
                if ($this->conf['showNewsImages'] ?? false) {
                    return $this->getFirstFalRelationUid(
                        'tx_news_domain_model_news',
                        'fal_media',
                        $row['orig_uid']
                    );
                }
                break;

            default:
                if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['fileReferenceTypes'] ?? null)
                    && isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['fileReferenceTypes'][$type])) {
                    return $this->getFirstFalRelationUid(
                        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['fileReferenceTypes'][$type]['table'],
                        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['fileReferenceTypes'][$type]['field'],
                        $row['orig_uid']
                    );
                }
                break;
        }

        return 0;
    }

    /**
     * get path for type icon used for rendering in fluid
     *
     * @param string $typeComplete
     * @return string the path to the type icon file
     */
    public function getTypeIconPath(string $typeComplete): string
    {
        list($type) = explode(':', $typeComplete);
        $name = str_replace(':', '_', $typeComplete);

        if ($this->conf['resultListTypeIcon'][$name] ?? false) {
            // custom icons defined by typoscript
            return $this->conf['resultListTypeIcon'][$name]['file'];
        }
        // default icons from ext:ke_search
        $extensionIconPath = 'EXT:ke_search/Resources/Public/Icons/types/' . $name . '.gif';
        if (is_file(GeneralUtility::getFileAbsFileName($extensionIconPath))) {
            return $extensionIconPath;
        }
        if ($type == 'file') {
            // fallback for file results: use default if no image for this file extension is available
            return 'EXT:ke_search/Resources/Public/Icons/types/file.gif';
        }
        // fallback if no icon found
        return 'EXT:ke_search/Resources/Public/Icons/types/default.gif';
    }

    public function getFirstFalRelationUid(string $table, string $field, int $uid): int
    {
        /** @var GenericRepository $genericRepository */
        $genericRepository = GeneralUtility::makeInstance(GenericRepository::class);
        /** @var FileReferenceRepository $fileReferenceRepository */
        $fileReferenceRepository = GeneralUtility::makeInstance(FileReferenceRepository::class);

        // Fetch result in current language or all languages and fallback to language 0.
        $languageOverlayRecord =
            ($this->languageId > 0)
                ? $genericRepository->findLangaugeOverlayByUidAndLanguage($table, $uid, $this->languageId)
                : null;

        $fileReferenceRow = $fileReferenceRepository->findOneByTableAndFieldnameAndUidForeignAndLanguage(
            $table,
            $field,
            $languageOverlayRecord['uid'] ?? $uid,
            [$this->languageId, -1]
        );

        if ($this->languageId > 0 && !is_array($fileReferenceRow)) {
            $fileReferenceRow = $fileReferenceRepository->findOneByTableAndFieldnameAndUidForeignAndLanguage(
                $table,
                $field,
                $uid
            );
        }

        return is_array($fileReferenceRow) ? $fileReferenceRow['uid'] : 0;
    }

    /**
     * Fetches configuration value given its name.
     * Merges flexform and TS configuration values.
     *
     * @param    string $param Configuration value name
     * @param    string $sheet
     * @return    string    Parameter value
     */
    public function fetchConfigurationValue(string $param, string $sheet = 'sDEF'): string
    {
        $value = trim(
            $this->pi_getFFvalue(
                $this->cObj->data['pi_flexform'],
                $param,
                $sheet
            )
        );
        return $value ?: ($this->conf[$param] ?? '');
    }

    /**
     * function betterSubstr
     * better substring function
     *
     * @param string $str
     * @param int $length
     * @param int $minword
     * @return string
     */
    public function betterSubstr(string $str, int $length = 0, int $minword = 3): string
    {
        $sub = '';
        $len = 0;
        foreach (explode(' ', $str) as $word) {
            $part = (($sub != '') ? ' ' : '') . $word;
            $sub .= $part;
            $len += strlen($part);
            if (strlen($word) > $minword && strlen($sub) >= $length) {
                break;
            }
        }
        return $sub . (($len < strlen($str)) ? '...' : '');
    }

    public function renderOrdering()
    {
        /** @var Sorting $sortObj */
        $sortObj = GeneralUtility::makeInstance(Sorting::class, $this);
        $sortObj->renderSorting($this->fluidTemplateVariables);
    }

    /*
     * count searchwords and phrases in statistic tables
     * assumes that charset ist UTF-8 and uses mb_strtolower
     *
     * @param $searchPhrase string
     * @param $searchWordsArray array
     * @param $hits int
     * @param $this->tagsAgainst string
     * @return void
     *
     */
    public function countSearchPhrase($searchPhrase, $searchWordsArray, $hits, $tagsAgainst)
    {
        // prepare "tagsAgainst"
        $search = ['"', ' ', '+'];
        $replace = ['', '', ''];
        $tagsAgainst = str_replace($search, $replace, implode(' ', $tagsAgainst));

        if (extension_loaded('mbstring')) {
            $searchPhrase = mb_strtolower($searchPhrase, 'UTF-8');
        } else {
            $searchPhrase = strtolower($searchPhrase);
        }

        // count search phrase
        if (!empty($searchPhrase)) {
            $table = 'tx_kesearch_stat_search';
            $fields_values = [
                'pid' => $this->firstStartingPoint,
                'searchphrase' => $searchPhrase,
                'tstamp' => time(),
                'hits' => $hits,
                'tagsagainst' => $tagsAgainst,
                'language' => $this->languageId,
            ];
            $queryBuilder = Db::getQueryBuilder($table);
            $queryBuilder
                ->insert($table)
                ->values($fields_values)
                ->executeStatement();
        }

        // count single words
        foreach ($searchWordsArray as $searchWord) {
            if (extension_loaded('mbstring')) {
                $searchWord = mb_strtolower($searchWord, 'UTF-8');
            } else {
                $searchWord = strtolower($searchWord);
            }
            $table = 'tx_kesearch_stat_word';
            if (!empty($searchWord)) {
                $queryBuilder = Db::getQueryBuilder($table);
                $fields_values = [
                    'pid' => $this->firstStartingPoint,
                    'word' => $searchWord,
                    'tstamp' => time(),
                    // @extensionScannerIgnoreLine
                    'pageid' => $GLOBALS['TSFE']->id,
                    'resultsfound' => $hits ? 1 : 0,
                    'language' => $this->languageId,
                ];
                $queryBuilder
                    ->insert($table)
                    ->values($fields_values)
                    ->executeStatement();
            }
        }
    }

    /**
     * gets all preselected filters from flexform
     * returns nothing but fills global var with needed data
     */
    public function getFilterPreselect()
    {
        // get definitions from plugin settings
        // and proceed only when preselectedFilter was not set
        // this reduces the amount of sql queries, too
        if (($this->conf['preselected_filters'] ?? false) && count($this->preselectedFilter) == 0) {
            $preselectedArray = GeneralUtility::intExplode(',', $this->conf['preselected_filters'], true);
            foreach ($preselectedArray as $option) {
                $queryBuilder = Db::getQueryBuilder('tx_kesearch_filters');
                /** @var PageRepository $pageRepository */
                $pageRepository = GeneralUtility::makeInstance(PageRepository::class);
                $filterRows = $queryBuilder
                    ->add(
                        'select',
                        '`tx_kesearch_filters`.`uid` AS filteruid, `tx_kesearch_filteroptions`.`uid` AS optionuid, `tx_kesearch_filteroptions`.`tag`'
                    )
                    ->from('tx_kesearch_filters')
                    ->from('tx_kesearch_filteroptions')
                    ->add(
                        'where',
                        'FIND_IN_SET("' . $option . '",tx_kesearch_filters.options)'
                        . ' AND `tx_kesearch_filteroptions`.`uid` = ' . $option .
                        // @extensionScannerIgnoreLine
                        $pageRepository->enableFields('tx_kesearch_filters') .
                        // @extensionScannerIgnoreLine
                        $pageRepository->enableFields('tx_kesearch_filteroptions')
                    )
                    ->executeQuery()
                    ->fetchAllAssociative();

                foreach ($filterRows as $row) {
                    $this->preselectedFilter[$row['filteruid']][$row['optionuid']] = $row['tag'];
                }
            }
        }
    }

    /**
     * function isEmptySearch
     * checks if an empty search was loaded / submitted
     * @return bool true if no searchparams given; otherwise false
     */
    public function isEmptySearch(): bool
    {
        // check if searchword is emtpy or equal with default searchbox value
        $emptySearchword = empty($this->sword) || $this->sword == $this->pi_getLL('searchbox_default_value');

        // check if filters are set
        $filters = $this->filters->getFilters();
        $filterSet = false;
        if (is_array($filters)) {
            foreach ($filters as $filter) {
                if (!empty($this->piVars['filter'][$filter['uid']])) {
                    $filterSet = true;
                }
            }
        }

        if ($emptySearchword && !$filterSet) {
            return true;
        }
        return false;
    }

    /**
     * @param string $eventUid The uid is passed as string, but we know that for Cal this is an integer
     * @return array
     */
    public function getCalEventEnddate(string $eventUid): array
    {
        $table = 'tx_cal_event';
        $queryBuilder = Db::getQueryBuilder($table);
        $row = $queryBuilder
            ->select('end_date', 'end_time', 'allday', 'start_date')
            ->from($table)
            ->where(
                $queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->createNamedParameter($eventUid, PDO::PARAM_INT)
                )
            )
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        return [
            'end_timestamp' => strtotime($row['end_date']) + $row['end_time'],
            'end_date' => strtotime($row['end_date']),
            'end_time' => $row['end_time'],
            'allday' => $row['allday'],
            'sameday' => ($row['end_date'] == $row['start_date']) ? 1 : 0,
        ];
    }

    /**
     * @param array $array
     * @param string $field
     * @return array
     */
    public function sortArrayRecursive(array $array, string $field): array
    {
        $sortArray = [];
        $mynewArray = [];

        $i = 1;
        foreach ($array as $point) {
            $sortArray[] = $point[$field] . $i;
            $i++;
        }
        rsort($sortArray);

        foreach ($sortArray as $sortet) {
            $i = 1;
            foreach ($array as $point) {
                $newpoint[$field] = $point[$field] . $i;
                if ($newpoint[$field] == $sortet) {
                    $mynewArray[] = $point;
                }
                $i++;
            }
        }
        return $mynewArray;
    }

    /**
     * @param array $wert_a
     * @param array $wert_b
     * @return int
     */
    public function sortArrayRecursive2($wert_a, $wert_b)
    {
        // sort using the second value of the array (index: 1)
        $a = $wert_a[2];
        $b = $wert_b[2];

        if ($a == $b) {
            return 0;
        }

        return ($a < $b) ? -1 : +1;
    }

    /**
     * implements a recursive in_array function
     * @param mixed $needle
     * @param array $haystack
     * @author Christian Bülter
     * @since 11.07.12
     * @return bool
     */
    public function in_multiarray($needle, array $haystack): bool
    {
        foreach ($haystack as $value) {
            if (is_array($value)) {
                if ($this->in_multiarray($needle, $value)) {
                    return true;
                }
            } else {
                if ($value == $needle) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Sort array by given column
     * @param array $arr the array
     * @param string $col the column
     */
    public function sortArrayByColumn(array &$arr, string $col): void
    {
        $newArray = [];
        $sort_col = [];
        foreach ($arr as $key => $row) {
            $sort_col[$key] = strtoupper($row[$col]);
        }
        asort($sort_col, SORT_LOCALE_STRING);

        foreach ($sort_col as $key => $val) {
            $newArray[$key] = $arr[$key];
        }

        $arr = $newArray;
    }

    /**
     * check for inequality to null to maintain functionality even if unset
     *
     * @return bool
     */
    private function allowEmptySearch(): bool
    {
        if ($this->extConf['allowEmptySearch'] != null && $this->extConf['allowEmptySearch'] == false) {
            return false;
        }
        return true;
    }

    public function getContentObjectRenderer(): ContentObjectRenderer
    {
        return $this->cObj;
    }

    public function setRequest(ServerRequestInterface $request)
    {
        if ($this->request === null) {
            $this->request = $request;
        }
    }
}
