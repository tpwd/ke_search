<?php

$langGeneralPath = 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:';

$txKesearchFilteroptionsTCA = [
    'ctrl' => [
        'title' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_filteroptions',
        'label' => 'title',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'languageField' => 'sys_language_uid',
        'transOrigPointerField' => 'l10n_parent',
        'transOrigDiffSourceField' => 'l10n_diffsource',
        'sortby' => 'sorting',
        'delete' => 'deleted',
        'enablecolumns' => [
            'disabled' => 'hidden',
        ],
        'security' => [
            'ignorePageTypeRestriction' => true,
        ],
        'typeicon_classes' => [
            'default' => 'form-checkbox',
        ],
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
                'items' => [
                    [
                        'label' => '',
                        'value' => 0,
                    ],
                ],
                'foreign_table' => 'tx_kesearch_filteroptions',
                'foreign_table_where' => 'AND tx_kesearch_filteroptions.pid=###CURRENT_PID###'
                    . ' AND tx_kesearch_filteroptions.sys_language_uid IN (-1,0)',
                'default' => 0,
            ],
        ],
        'l10n_diffsource' => [
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'title' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_filteroptions.title',
            'config' => [
                'type' => 'input',
                'size' => '30',
            ],
        ],
        'tag' => [
            'exclude' => 0,
            'l10n_mode' => 'exclude',
            'l10n_display' => 'defaultAsReadonly',
            'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_filteroptions.tag',
            'config' => [
                'type' => 'input',
                'size' => '30',
                'eval' => 'trim,alphanum,' . \Tpwd\KeSearch\UserFunction\CustomFieldValidation\FilterOptionTagValidator::class,
                'required' => true,
            ],
        ],
        'slug' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_filteroptions.slug',
            'config' => [
                'type' => 'slug',
                'size' => 50,
                'generatorOptions' => [
                    'fields' => ['title'],
                    'fieldSeparator' => '/',
                    'prefixParentPageSlug' => false,
                ],
                'fallbackCharacter' => '-',
                'eval' => 'uniqueInSite',
                'default' => '',
            ],
        ],
        'automated_tagging' => [
            'exclude' => 1,
            'l10n_mode' => 'exclude',
            'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_filteroptions.automated_tagging',
            'config' => [
                'type' => 'group',
                'allowed' => 'pages',
                'size' => 5,
                'minitems' => 0,
                'maxitems' => 99,
            ],
        ],
        'automated_tagging_exclude' => [
            'exclude' => 1,
            'l10n_mode' => 'exclude',
            'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_filteroptions.automated_tagging_exclude',
            'config' => [
                'type' => 'group',
                'allowed' => 'pages',
                'size' => 5,
                'minitems' => 0,
                'maxitems' => 99,
            ],
        ],
    ],
    'types' => [
        '0' => ['showitem' => 'sys_language_uid, l10n_parent, l10n_diffsource, hidden,'
            . ' title, tag, slug, automated_tagging, automated_tagging_exclude', ],
    ],
];

if (TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(TYPO3\CMS\Core\Information\Typo3Version::class)->getMajorVersion() < 14) {
    $txKesearchFilteroptionsTCA['ctrl']['searchFields'] = 'title, tag, slug';
}

return $txKesearchFilteroptionsTCA;
