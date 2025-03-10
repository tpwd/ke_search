<?php

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

use Psr\Http\Message\ServerRequestInterface;
use Tpwd\KeSearchPremium\Headless\HeadlessApi;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Pagination\ArrayPaginator;
use TYPO3\CMS\Core\Pagination\SlidingWindowPagination;
use TYPO3\CMS\Core\TypoScript\TypoScriptService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\CMS\Fluid\View\StandaloneView;

/**
 * Plugin 'Faceted search - resultlist plugin' for the 'ke_search' extension.
 * @author    Andreas Kiefer
 * @author    Christian Bülter
 */
class ResultlistPlugin extends PluginBase
{
    // @phpstan-ignore-next-line
    private ?ViewFactoryInterface $viewFactory;

    // TODO: Inject ViewFactoryInterface once TYPO3 v13 is the minimum requirement
    //public function __construct(
    //private readonly ViewFactoryInterface $viewFactory,
    //) {}
    public function __construct()
    {
        if (GeneralUtility::makeInstance(Typo3Version::class)->getMajorVersion() > 12) {
            // @phpstan-ignore-next-line
            $this->viewFactory = GeneralUtility::makeInstance(ViewFactoryInterface::class);
        }
    }

    /**
     * The main method of the PlugIn
     *
     * @param string $content The PlugIn content
     * @param array $conf The PlugIn configuration
     * @param ServerRequestInterface $request
     * @return string The content that is displayed on the website
     */
    public function main(string $content, array $conf, ServerRequestInterface $request): string
    {
        $typoScriptService = GeneralUtility::makeInstance(TypoScriptService::class);
        $this->conf = $conf;
        $this->setLanguageFile('EXT:ke_search/Resources/Private/Language/locallang_searchbox.xlf');
        $this->conf = $typoScriptService->convertTypoScriptArrayToPlainArray($conf);

        // initializes plugin configuration
        // @extensionScannerIgnoreLine
        $this->init($request);

        if ($this->conf['resultPage'] != $GLOBALS['TSFE']->id) {
            $content = '<div id="textmessage">' . $this->translate('error_resultPage') . '</div>';
            return $this->pi_wrapInBaseClass($content);
        }

        // Check if "Static Templates" / "Site Sets" have been included
        if (empty($this->conf['view'])) {
            $content = '<div id="textmessage">' . $this->translate('error_templatePaths') . '</div>';
            return $this->pi_wrapInBaseClass($content);
        }

        // Initialize the view
        if (GeneralUtility::makeInstance(Typo3Version::class)->getMajorVersion() > 12) {
            // @phpstan-ignore-next-line
            $viewFactoryData = new ViewFactoryData(
                templateRootPaths: $this->conf['view']['templateRootPaths'],
                partialRootPaths: $this->conf['view']['partialRootPaths'],
                layoutRootPaths: $this->conf['view']['layoutRootPaths'],
                request: $this->request,
            );
            // @phpstan-ignore-next-line
            $view = $this->viewFactory->create($viewFactoryData);
        } else {
            $view = GeneralUtility::makeInstance(StandaloneView::class);
            if (method_exists($view, 'setRequest')) {
                $view->setRequest($GLOBALS['TYPO3_REQUEST']);
            }
            $view->setTemplateRootPaths($this->conf['view']['templateRootPaths']);
            $view->setPartialRootPaths($this->conf['view']['partialRootPaths']);
            $view->setLayoutRootPaths($this->conf['view']['layoutRootPaths']);
            $view->setTemplate('ResultList');
        }

        // Make settings available in fluid template
        $view->assign('conf', $this->conf);
        $view->assign('extConf', $this->extConf);
        $view->assign('extConfPremium', $this->extConfPremium);

        // hook for initials
        if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['initials'] ?? null)) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['initials'] as $_classRef) {
                $_procObj = GeneralUtility::makeInstance($_classRef);
                $_procObj->addInitials($this);
            }
        }

        // assign isEmptySearch to fluid templates
        $this->fluidTemplateVariables['isEmptySearch'] = $this->isEmptySearch;

        // render "no results"-message, "too short words"-message and finally the result list
        $this->getSearchResults();

        // number of results
        $this->fluidTemplateVariables['numberofresults'] = $this->numberOfResults;

        // render links for sorting, fluid template variables are filled in class Sorting
        $this->renderOrdering();

        // process query time
        $queryTime = (round(microtime(true) * 1000) - $this->queryStartTime);
        $this->fluidTemplateVariables['queryTime'] = $queryTime;
        $this->fluidTemplateVariables['queryTimeText'] = sprintf($this->translate('query_time'), $queryTime);

        // get pagination
        $itemsPerPage = (int)($this->conf['resultsPerPage'] ?? 10);
        $maxPages = (int)($this->conf['maxPagesInPagebrowser'] ?? 10);
        // In order to use the built-in paginator feature of TYPO3 we need to fill an array because
        // we don't have the full list of search results available
        $dummyResults = array_fill(0, $this->numberOfResults, 1);
        $paginator = new ArrayPaginator($dummyResults, $this->piVars['page'], $itemsPerPage);
        $this->fluidTemplateVariables['pagination'] = new SlidingWindowPagination($paginator, $maxPages);

        // hook: modifyResultList
        if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['modifyResultList'] ?? null)) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['modifyResultList'] as $_classRef) {
                $_procObj = GeneralUtility::makeInstance($_classRef);
                $_procObj->modifyResultList($this->fluidTemplateVariables, $this);
            }
        }

        if (class_exists('\Tpwd\KeSearchPremium\Headless\HeadlessApi')) {
            /** @var \Tpwd\KeSearchPremium\Headless\HeadlessApi $headlessApi */
            $headlessApi = GeneralUtility::makeInstance(HeadlessApi::class);
            if ($headlessApi->getHeadlessMode()) {
                return json_encode($this->fluidTemplateVariables);
            }
        }

        // generate HTML output
        $view->assignMultiple($this->fluidTemplateVariables);
        if (GeneralUtility::makeInstance(Typo3Version::class)->getMajorVersion() > 12) {
            $htmlOutput = $view->render('ResultList');
        } else {
            $htmlOutput = $view->render();
        }

        return $htmlOutput;
    }
}
