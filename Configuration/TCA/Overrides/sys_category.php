<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

// add field tx_keasearch_filter
ExtensionManagementUtility::addTCAcolumns(
    'sys_category',
    [
        'tx_kesearch_filter' => [
            'l10n_mode' => 'exclude',
            'l10n_display' => 'defaultAsReadonly',
            'exclude' => 1,
            'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:sys_category.tx_kesearch_filter',
            'config' => [
                'default' => 0,
                'type' => 'select',
                'renderType' => 'selectMultipleSideBySide',
                'foreign_table' => 'tx_kesearch_filters',
                'foreign_table_where' => ' AND tx_kesearch_filters.sys_language_uid IN (-1,0)',
                'size' => 10,
                'minitems' => 0,
                'maxitems' => 99,
            ],
        ],
        'tx_kesearch_filtersubcat' => [
            'l10n_mode' => 'exclude',
            'l10n_display' => 'defaultAsReadonly',
            'exclude' => 1,
            'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:sys_category.tx_kesearch_filtersubcat',
            'config' => [
                'default' => 0,
                'type' => 'select',
                'renderType' => 'selectMultipleSideBySide',
                'foreign_table' => 'tx_kesearch_filters',
                'foreign_table_where' => ' AND tx_kesearch_filters.sys_language_uid IN (-1,0)',
                'size' => 10,
                'minitems' => 0,
                'maxitems' => 99,
            ],
        ],
    ]
);
ExtensionManagementUtility::addToAllTCAtypes(
    'sys_category',
    '--div--;LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:pages.tx_kesearch_label,tx_kesearch_filter,tx_kesearch_filtersubcat'
);
