<?php

defined('TYPO3') or die();

(function () {
    // TODO: Remove this once support for TYPO3 11 is dropped
    // @extensionScannerIgnoreLine
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addModule(
        'web',
        'KeSearchBackendModule',
        '',
        null,
        [
            'routeTarget' => \Tpwd\KeSearch\Controller\BackendModuleController::class,
            'access' => 'user,group',
            'name' => 'web_KeSearchBackendModule',
            'icon' => 'EXT:ke_search/Resources/Public/Icons/moduleicon.svg',
            'labels' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_mod.xlf',
        ]
    );
})();
