<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

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
                'items' => [
                    [
                        0 => '',
                        'invertStateDisplay' => true,
                    ],
                ],
            ],
        ],
    ]
);

ExtensionManagementUtility::addToAllTCAtypes(
    'sys_file_metadata',
    '--div--;LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:sys_file_metadata.tx_kesearch_label,tx_kesearch_no_search'
);
