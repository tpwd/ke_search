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
use TYPO3\CMS\Core\TypoScript\TypoScriptService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\CMS\Fluid\View\StandaloneView;

/**
 * Plugin 'Faceted search - searchbox and filters' for the 'ke_search' extension.
 * @author    Andreas Kiefer
 * @author    Christian Bülter
 */
class SearchboxPlugin extends PluginBase
{
    private ?ViewFactoryInterface $viewFactory;

    // TODO: Inject ViewFactoryInterface once TYPO3 v13 is the minimum requirement
    //public function __construct(
    //private readonly ViewFactoryInterface $viewFactory,
    //) {}
    public function __construct()
    {
        if (GeneralUtility::makeInstance(Typo3Version::class)->getMajorVersion() > 12) {
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

        // Check if "Static Templates" / "Site Sets" have been included
        if (empty($this->conf['view'])) {
            $content = '<div id="textmessage">' . $this->translate('error_templatePaths') . '</div>';
            return $this->pi_wrapInBaseClass($content);
        }

        // Initialize the view
        if (GeneralUtility::makeInstance(Typo3Version::class)->getMajorVersion() > 12) {
            $viewFactoryData = new ViewFactoryData(
                templateRootPaths: $this->conf['view']['templateRootPaths'],
                partialRootPaths: $this->conf['view']['partialRootPaths'],
                layoutRootPaths: $this->conf['view']['layoutRootPaths'],
                request: $this->request,
            );
            $view = $this->viewFactory->create($viewFactoryData);
        } else {
            $view = GeneralUtility::makeInstance(StandaloneView::class);
            if (method_exists($view, 'setRequest')) {
                $view->setRequest($GLOBALS['TYPO3_REQUEST']);
            }
            $view->setTemplateRootPaths($this->conf['view']['templateRootPaths']);
            $view->setPartialRootPaths($this->conf['view']['partialRootPaths']);
            $view->setLayoutRootPaths($this->conf['view']['layoutRootPaths']);
            $view->setTemplate('SearchForm');
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

        // get content for searchbox
        $this->getSearchboxContent();

        if (class_exists('\Tpwd\KeSearchPremium\Headless\HeadlessApi')) {
            /** @var \Tpwd\KeSearchPremium\Headless\HeadlessApi $headlessApi */
            $headlessApi = GeneralUtility::makeInstance(HeadlessApi::class);
            if ($headlessApi->getHeadlessMode()) {
                return json_encode($this->fluidTemplateVariables);
            }
        }

        // assign variables and do the rendering
        $view->assignMultiple($this->fluidTemplateVariables);
        if (GeneralUtility::makeInstance(Typo3Version::class)->getMajorVersion() > 12) {
            $htmlOutput = $view->render('SearchForm');
        } else {
            $htmlOutput = $view->render();
        }

        return $htmlOutput;
    }
}
