<?php

defined('TYPO3') or die();

(function () {
    // TODO: Remove this once support for TYPO3 11 is dropped
    \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
        'KeSearch',
        'web',
        'backend_module',
        '',
        [
            \Tpwd\KeSearch\Controller\BackendModuleController::class => 'startIndexing, indexedContent, indexTableInformation, searchwordStatistics, clearSearchIndex, lastIndexingReport, alert',
        ],
        [
            'access' => 'user,group',
            'icon' => 'EXT:ke_search/Resources/Public/Icons/moduleicon.svg',
            'labels' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_mod.xlf',
        ]
    );
})();
