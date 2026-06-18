<?php

use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\GeneralUtility;

return [
    'ke_search' => [
        'title' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_dashboard.xlf:dashboard_preset.title',
        'description' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_dashboard.xlf:dashboard_preset.description',
        'iconIdentifier' => GeneralUtility::makeInstance(Typo3Version::class)->getMajorVersion() < 14 ? 'ext-kesearch-wizard-icon-old' : 'ext-kesearch-wizard-icon',
        'defaultWidgets' => ['keSearchIndexOverview', 'keSearchTrendingSearchphrases', 'keSearchStatus'],
        'showInWizard' => true,
    ],
];
