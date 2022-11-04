<?php

$langGeneralPath = 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:';

return [
    'ctrl' => [
        'title' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_filters',
        'label' => 'title',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'cruser_id' => 'cruser_id',
        'languageField' => 'sys_language_uid',
        'transOrigPointerField' => 'l10n_parent',
        'transOrigDiffSourceField' => 'l10n_diffsource',
        'default_sortby' => 'ORDER BY crdate',
        'delete' => 'deleted',
        'type' => 'rendertype',
        'enablecolumns' => [
            'disabled' => 'hidden',
        ],
        'iconfile' => 'EXT:ke_search/Resources/Public/Icons/table_icons/icon_tx_kesearch_filters.gif',
        'searchFields' => 'title',
    ],
    'columns' => [
        'sys_language_uid' => [
            'exclude' => 1,
            'label' => $langGeneralPath . 'LGL.language',
            'config' => [
                'default' => 0,
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'sys_language',
                'foreign_table_where' => 'ORDER BY sys_language.title',
                'items' => [
                    [$langGeneralPath . 'LGL.allLanguages', -1],
                    [$langGeneralPath . 'LGL.default_value', 0],
                ],
            ],
        ],
        'l10n_parent' => [
            'displayCond' => 'FIELD:sys_language_uid:>:0',
            'label' => $langGeneralPath . 'LGL.l18n_parent',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['', 0],
                ],
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
                'items' => [
                    ['LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_filters.rendertype.I.0', 'select'],
                    ['LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_filters.rendertype.I.1', 'list'],
                    ['LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_filters.rendertype.I.2', 'checkbox'],
                    ['LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_filters.rendertype.I.3', 'dateRange'],
                ],
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
                'internal_type' => 'db',
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
            'config' => [
                'type' => 'input',
                'default' => '10',
                'size' => '30',
                'eval' => 'trim,int',
            ],
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
