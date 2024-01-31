<?php

use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\GeneralUtility;

$langGeneralPath = 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:';
$typo3Version = GeneralUtility::makeInstance(Typo3Version::class);
$typo3MajorVersion = $typo3Version->getMajorVersion();
$typo3BranchVersion = (float)$typo3Version->getBranch();

if ($typo3MajorVersion < 12) {
    $starttimeConfigArray = [
        'type' => 'input',
        'size' => '8',
        'eval' => 'date',
        'renderType' => 'inputDateTime',
        'default' => '0',
        'checkbox' => '0',
    ];
    $endtimeConfigArray = [
        'type' => 'input',
        'size' => '8',
        'eval' => 'date',
        'renderType' => 'inputDateTime',
        'default' => '0',
        'checkbox' => '0',
    ];
    $sortdateConfigArray = [
        'type' => 'input',
        'renderType' => 'inputDateTime',
        'size' => '10',
        'eval' => 'datetime',
        'checkbox' => '0',
        'default' => '0',
    ];
} else {
    $starttimeConfigArray = [
        'type' => 'datetime',
        'size' => '8',
        'format' => 'date',
        'default' => '0',
        'checkbox' => '0',
    ];
    $endtimeConfigArray = [
        'type' => 'datetime',
        'size' => '8',
        'format' => 'date',
        'default' => '0',
        'checkbox' => '0',
    ];
    $sortdateConfigArray = [
        'type' => 'datetime',
        'size' => '10',
        'checkbox' => '0',
        'default' => '0',
    ];
}

if ($typo3BranchVersion >= 12.3) {
    $feGroupItemsArray = [
        [
            'label' => '',
            'value' => 0,
        ],
        [
            'label' => $langGeneralPath . 'LGL.hide_at_login',
            'value' => -1,
        ],
        [
            'label' => $langGeneralPath . 'LGL.any_login',
            'value' => -2,
        ],
        [
            'label' => $langGeneralPath . 'LGL.usergroups',
            'value' => '--div--',
        ],
    ];
} else {
    $feGroupItemsArray = [
        ['', 0],
        [$langGeneralPath . 'LGL.hide_at_login', -1],
        [$langGeneralPath . 'LGL.any_login', -2],
        [$langGeneralPath . 'LGL.usergroups', '--div--'],
    ];
}

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
        'iconfile' => 'EXT:ke_search/Resources/Public/Icons/table_icons/icon_tx_kesearch_index.gif',
    ],
    'columns' => [
        'starttime' => [
            'exclude' => 1,
            'label' => $langGeneralPath . 'LGL.starttime',
            'config' => $starttimeConfigArray,
        ],
        'endtime' => [
            'exclude' => 1,
            'label' => $langGeneralPath . 'LGL.endtime',
            'config' => $endtimeConfigArray,
        ],
        'fe_group' => [
            'exclude' => 1,
            'label' => $langGeneralPath . 'LGL.fe_group',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingleBox',
                'items' => $feGroupItemsArray,
                'foreign_table' => 'fe_groups',
                'foreign_table_where' => 'ORDER BY fe_groups.title',
                'size' => 6,
                'minitems' => 0,
                'maxitems' => 99999,
            ],
        ],
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
            'config' => $sortdateConfigArray,
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

if ($typo3MajorVersion < 12) {
    $txKesearchIndex['ctrl']['cruser_id'] = 'cruser_id';
}

return $txKesearchIndex;
