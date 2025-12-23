<?php

$langGeneralPath = 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:';

$txKesearchIndex = [
    'ctrl' => [
        'title' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_index',
        'label' => 'title',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'default_sortby' => 'ORDER BY crdate',
        'enablecolumns' => [
            'starttime' => 'starttime',
            'endtime' => 'endtime',
            'fe_group' => 'fe_group',
        ],
        'security' => [
            'ignorePageTypeRestriction' => true,
        ],
        'typeicon_classes' => [
            'default' => 'content-database',
        ],
    ],
    'columns' => [
        'targetpid' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_index.targetpid',
            'config' => [
                'type' => 'group',
                'allowed' => 'pages',
                'size' => 1,
                'minitems' => 0,
                'maxitems' => 1,
            ],
        ],
        'content' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_index.content',
            'config' => [
                'type' => 'text',
                'wrap' => 'OFF',
                'cols' => '30',
                'rows' => '5',
            ],
        ],
        'hidden_content' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_index.hidden_content',
            'description' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_index.hidden_content.description',
            'config' => [
                'type' => 'text',
                'wrap' => 'OFF',
                'cols' => '30',
                'rows' => '5',
            ],
        ],
        'params' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_index.params',
            'config' => [
                'type' => 'input',
                'size' => '30',
            ],
        ],
        'type' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_index.type',
            'config' => [
                'type' => 'input',
                'size' => '30',
            ],
        ],
        'tags' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_index.tags',
            'config' => [
                'type' => 'text',
                'wrap' => 'OFF',
                'cols' => '30',
                'rows' => '5',
            ],
        ],
        'abstract' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_index.abstract',
            'config' => [
                'type' => 'text',
                'cols' => '30',
                'rows' => '5',
            ],
        ],
        'title' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_index.title',
            'config' => [
                'type' => 'input',
                'size' => '30',
            ],
        ],
        'language' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_index.language',
            'config' => [
                'type' => 'language',
            ],
        ],
        'sortdate' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_index.sortdate',
            'config' => [
                'type' => 'datetime',
                'size' => '10',
                'checkbox' => '0',
                'default' => '0',
            ],
        ],
        'orig_uid' => [
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'orig_pid' => [
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'directory' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_index.directory',
            'config' => [
                'type' => 'input',
                'size' => '30',
            ],
        ],
        'hash' => [
            'config' => [
                'type' => 'passthrough',
            ],
        ],
    ],
    'types' => [
        '0' => ['showitem' => 'starttime, endtime, fe_group, targetpid, content, hidden_content,'
            . ' params, type, tags, abstract, title, language', ],
    ],
    'palettes' => [
        '1' => ['showitem' => ''],
    ],
];

return $txKesearchIndex;
