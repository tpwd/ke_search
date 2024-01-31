<?php

use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\GeneralUtility;

$langGeneralPath = 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:';
$typo3Version = GeneralUtility::makeInstance(Typo3Version::class);
$typo3MajorVersion = $typo3Version->getMajorVersion();
$typo3BranchVersion = (float)$typo3Version->getBranch();

if ($typo3MajorVersion > 11) {
    $amountConfigArray = [
        'type' => 'number',
        'default' => '10',
        'size' => '30',
    ];
} else {
    $amountConfigArray = [
        'type' => 'input',
        'default' => '10',
        'size' => '30',
        'eval' => 'trim,int',
    ];
}
if ($typo3BranchVersion >= 12.3) {
    $l10nParentItemsArray = [
        [
            'label' => '',
            'value' => 0,
        ],
    ];
    $rendertypeItemsArray = [
        [
            'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_filters.rendertype.I.0',
            'value' => 'select',
        ],
        [
            'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_filters.rendertype.I.1',
            'value' => 'list',
        ],
        [
            'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_filters.rendertype.I.2',
            'value' => 'checkbox',
        ],
        [
            'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_filters.rendertype.I.3',
            'value' => 'dateRange',
        ],
    ];
} else {
    $l10nParentItemsArray = [
        ['', 0],
    ];
    $rendertypeItemsArray = [
        ['LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_filters.rendertype.I.0', 'select'],
        ['LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_filters.rendertype.I.1', 'list'],
        ['LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_filters.rendertype.I.2', 'checkbox'],
        ['LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_filters.rendertype.I.3', 'dateRange'],
    ];
}

$txKesearchFiltersTCA = [
    'ctrl' => [
        'title' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_filters',
        'label' => 'title',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'languageField' => 'sys_language_uid',
        'transOrigPointerField' => 'l10n_parent',
        'transOrigDiffSourceField' => 'l10n_diffsource',
        'default_sortby' => 'ORDER BY crdate',
        'delete' => 'deleted',
        'type' => 'rendertype',
        'enablecolumns' => [
            'disabled' => 'hidden',
        ],
        'security' => [
            'ignorePageTypeRestriction' => true,
        ],
        'iconfile' => 'EXT:ke_search/Resources/Public/Icons/table_icons/icon_tx_kesearch_filters.gif',
        'searchFields' => 'title',
    ],
    'columns' => [
        'sys_language_uid' => [
            'exclude' => 1,
            'label' => $langGeneralPath . 'LGL.language',
            'config' => ['type' => 'language'],
        ],
        'l10n_parent' => [
            'displayCond' => 'FIELD:sys_language_uid:>:0',
            'label' => $langGeneralPath . 'LGL.l18n_parent',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => $l10nParentItemsArray,
                'foreign_table' => 'tx_kesearch_filters',
                'foreign_table_where' => 'AND tx_kesearch_filters.pid=###CURRENT_PID###'
                    . ' AND tx_kesearch_filters.sys_language_uid IN (-1,0)',
            ],
        ],
        'l10n_diffsource' => [
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'hidden' => [
            'exclude' => 1,
            'label' => $langGeneralPath . 'LGL.hidden',
            'config' => [
                'type' => 'check',
                'default' => '0',
            ],
        ],
        'title' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_filters.title',
            'config' => [
                'type' => 'input',
                'size' => '30',
            ],
        ],
        'rendertype' => [
            'exclude' => 0,
            'l10n_display' => 'defaultAsReadonly',
            'displayCond' => 'FIELD:l10n_parent:<:1',
            'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_filters.rendertype',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => $rendertypeItemsArray,
                'size' => 1,
                'maxitems' => 1,
                'default' => 'select',
            ],
        ],

        'markAllCheckboxes' => [
            'exclude' => 0,
            'l10n_mode' => 'exclude',
            'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_filters.markAllCheckboxes',
            'config' => [
                'type' => 'check',
                'default' => '0',
            ],
        ],
        'options' => [
            'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_filters.options',
            'l10n_mode' => 'exclude',
            'config' => [
                'type' => 'inline',
                'foreign_table' => 'tx_kesearch_filteroptions',
                'maxitems' => 500,
                'appearance' => [
                    'collapseAll' => true,
                    'expandSingle' => true,
                    'useSortable' => true,
                    'showPossibleLocalizationRecords' => true,
                    'showAllSynchronizationLink' => true,
                    'showSynchronizationLink' => true,
                    'enabledControls' => [
                        'info' => true,
                        'dragdrop' => true,
                        'sort' => true,
                        'hide' => true,
                        'delete' => true,
                        'localize' => true,
                    ],
                ],
            ],
        ],
        'target_pid' => [
            'exclude' => 1,
            'l10n_mode' => 'exclude',
            'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_filters.target_pid',
            'config' => [
                'default' => 0,
                'type' => 'group',
                'allowed' => 'pages',
                'size' => 1,
                'minitems' => 0,
                'maxitems' => 1,
            ],
        ],
        'amount' => [
            'exclude' => 0,
            'l10n_mode' => 'exclude',
            'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_filters.amount',
            'config' => $amountConfigArray,
        ],
        'shownumberofresults' => [
            'exclude' => 0,
            'l10n_mode' => 'exclude',
            'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_filters.shownumberofresults',
            'config' => [
                'type' => 'check',
                'default' => '1',
            ],
        ],
        'alphabeticalsorting' => [
            'exclude' => 0,
            'l10n_mode' => 'exclude',
            'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_filters.alphabeticalsorting',
            'config' => [
                'type' => 'check',
                'default' => '1',
            ],
        ],
    ],
    'types' => [
        'select' => ['showitem' => 'sys_language_uid,l10n_parent, l10n_diffsource, hidden,'
            . ' title,rendertype, options, shownumberofresults, alphabeticalsorting', ],
        'list' => ['showitem' => 'sys_language_uid,l10n_parent, l10n_diffsource, hidden,'
            . ' title,rendertype, options, shownumberofresults, alphabeticalsorting', ],
        'checkbox' => ['showitem' => 'sys_language_uid,l10n_parent, l10n_diffsource, hidden,'
            . ' title,rendertype, markAllCheckboxes, options, shownumberofresults,'
            . ' alphabeticalsorting', ],
        'dateRange' => ['showitem' => 'sys_language_uid,l10n_parent, l10n_diffsource, hidden,'
            . ' title,rendertype', ],
    ],
];

if ($typo3MajorVersion < 12) {
    $txKesearchFiltersTCA['ctrl']['cruser_id'] = 'cruser_id';
}

return $txKesearchFiltersTCA;
