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
use TYPO3\CMS\Fluid\View\StandaloneView;

/**
 * Plugin 'Faceted search - searchbox and filters' for the 'ke_search' extension.
 * @author    Andreas Kiefer
 * @author    Christian Bülter
 */
class SearchboxPlugin extends PluginBase
{
    /**
     * @var StandaloneView
     */
    protected $searchFormView;

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
        $this->pi_loadLL('EXT:ke_search/Resources/Private/Language/locallang_searchbox.xlf');
        $this->conf = $typoScriptService->convertTypoScriptArrayToPlainArray($conf);

        // initializes plugin configuration
        // @extensionScannerIgnoreLine
        $this->init($request);

        if (empty($this->conf['view'])) {
            $content = '<div id="textmessage">' . $this->pi_getLL('error_templatePaths') . '</div>';
            return $this->pi_wrapInBaseClass($content);
        }

        // init template for search box
        $this->initFluidTemplate();

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
        $this->searchFormView->assignMultiple($this->fluidTemplateVariables);
        $htmlOutput = $this->searchFormView->render();

        return $htmlOutput;
    }

    /**
     * inits the standalone fluid template
     */
    public function initFluidTemplate()
    {
        $this->searchFormView = GeneralUtility::makeInstance(StandaloneView::class);
        if (
            GeneralUtility::makeInstance(Typo3Version::class)->getMajorVersion() > 11
            && method_exists($this->searchFormView, 'setRequest')
        ) {
            $this->searchFormView->setRequest($GLOBALS['TYPO3_REQUEST']);
        }
        $this->searchFormView->setTemplateRootPaths($this->conf['view']['templateRootPaths']);
        $this->searchFormView->setPartialRootPaths($this->conf['view']['partialRootPaths']);
        $this->searchFormView->setLayoutRootPaths($this->conf['view']['layoutRootPaths']);
        $this->searchFormView->setTemplate('SearchForm');

        // make settings available in fluid template
        $this->searchFormView->assign('conf', $this->conf);
        $this->searchFormView->assign('extConf', $this->extConf);
        $this->searchFormView->assign('extConfPremium', $this->extConfPremium);
    }
}
