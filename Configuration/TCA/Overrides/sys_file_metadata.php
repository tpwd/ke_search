<?php

use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

defined('TYPO3') or die();

if ((float)GeneralUtility::makeInstance(Typo3Version::class)->getBranch() >= 12.3) {
    $txKesearchNoSearchItemsArray = [
        [
            'label' => '',
            'labelChecked' => '',
            'labelUnchecked' => '',
            'invertStateDisplay' => true,
        ],
    ];
} else {
    $txKesearchNoSearchItemsArray = [
        [
            0 => '',
            'invertStateDisplay' => true,
        ],
    ];
}

ExtensionManagementUtility::addTCAcolumns(
    'sys_file_metadata',
    [
        'tx_kesearch_no_search' => [
            'exclude' => 1,
            'l10n_mode' => 'exclude',
            'l10n_display' => 'defaultAsReadonly',
            'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:sys_file_metadata.tx_kesearch_no_search',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'items' => $txKesearchNoSearchItemsArray,
            ],
        ],
    ]
);

ExtensionManagementUtility::addToAllTCAtypes(
    'sys_file_metadata',
    '--div--;LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:sys_file_metadata.tx_kesearch_label,tx_kesearch_no_search'
);
