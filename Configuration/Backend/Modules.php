<?php

use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\GeneralUtility;

return [
    'web_KeSearchBackendModule' => [
        'parent' => 'web',
        'position' => 'bottom',
        'access' => 'user',
        'workspaces' => 'live',
        'iconIdentifier' => GeneralUtility::makeInstance(Typo3Version::class)->getMajorVersion() < 14 ? 'ext-kesearch-wizard-icon-old' : 'ext-kesearch-wizard-icon',
        'labels' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_mod.xlf',
        'routes' => [
            '_default' => [
                'target' => \Tpwd\KeSearch\Controller\BackendModuleController::class,
            ],
        ],
    ],
];
