<?php
use TYPO3\CMS\Core\Domain\Repository\PageRepository;

$langGeneralPath = 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:';

$txKesearchIndexerconfig = [
    'ctrl' => [
        'title' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_indexerconfig',
        'label' => 'title',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'default_sortby' => 'ORDER BY crdate',
        'delete' => 'deleted',
        'enablecolumns' => [
            'disabled' => 'hidden',
        ],
        'security' => [
            'ignorePageTypeRestriction' => true,
        ],
        'iconfile' => 'EXT:ke_search/Resources/Public/Icons/table_icons/icon_tx_kesearch_indexerconfig.gif',
        'searchFields' => 'title',
    ],
    'columns' => [
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
            'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_indexerconfig.title',
            'config' => [
                'type' => 'input',
                'size' => '30',
            ],
        ],
        'type' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_indexerconfig.type',
            'onChange' => 'reload',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    [
                        'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_indexerconfig.type.I.0',
                        'value' => 'page',
                        'icon'  => 'EXT:ke_search/Resources/Public/Icons/types_backend/selicon_tx_kesearch_indexerconfig_type_0.gif',
                    ],
                    [
                        'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_indexerconfig.type.I.12',
                        'value' => 'news',
                        'icon'  => 'EXT:ke_search/Resources/Public/Icons/types_backend/selicon_tx_kesearch_indexerconfig_type_12.gif',
                    ],
                    [
                        'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_indexerconfig.type.I.5',
                        'value' => 'tt_address',
                        'icon'  => 'EXT:ke_search/Resources/Public/Icons/types_backend/selicon_tx_kesearch_indexerconfig_type_5.gif',
                    ],
                    [
                        'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_indexerconfig.type.I.6',
                        'value' => 'tt_content',
                        'icon'  => 'EXT:ke_search/Resources/Public/Icons/types_backend/selicon_tx_kesearch_indexerconfig_type_6.gif',
                    ],
                    [
                        'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_indexerconfig.type.I.7',
                        'value' => 'file',
                        'icon'  => 'EXT:ke_search/Resources/Public/Icons/types_backend/selicon_tx_kesearch_indexerconfig_type_7.gif',
                    ],
                    [
                        'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_indexerconfig.type.I.2',
                        'value' => 'tt_news',
                        'icon'  => 'EXT:ke_search/Resources/Public/Icons/types_backend/selicon_tx_kesearch_indexerconfig_type_2.gif',
                    ],
                ],
                'itemsProcFunc' => 'Tpwd\KeSearch\Lib\Items->fillIndexerConfig',
                'size' => 1,
                'maxitems' => 1,
                'default' => 'page',
            ],
        ],
        'storagepid' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_indexerconfig.storagepid',
            'description' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_indexerconfig.storagepid.description',
            'config' => [
                'type' => 'group',
                'allowed' => 'pages',
                'size' => 1,
                'minitems' => 1,
                'maxitems' => 1,
            ],
        ],
        'targetpid' => [
            'displayCond' => 'FIELD:type:!IN:page,tt_content,file,remote',
            'exclude' => 0,
            'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_indexerconfig.targetpid',
            'config' => [
                'type' => 'group',
                'allowed' => 'pages',
                'size' => 1,
                'minitems' => 1,
                'maxitems' => 1,
            ],
        ],
        'startingpoints_recursive' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_indexerconfig.startingpoints_recursive',
            'description' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_indexerconfig.startingpoints_recursive.description',
            'displayCond' => 'FIELD:type:IN:page,tt_content,tt_address,news,tt_news',
            'config' => [
                'type' => 'group',
                'allowed' => 'pages',
                'size' => 10,
                'minitems' => 0,
                'maxitems' => 99,
            ],
        ],
        'single_pages' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_indexerconfig.single_pages',
            'description' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_indexerconfig.single_pages.description',
            'displayCond' => 'FIELD:type:IN:page,tt_content',
            'config' => [
                'type' => 'group',
                'allowed' => 'pages',
                'size' => 10,
                'minitems' => 0,
                'maxitems' => 99,
            ],
        ],
        'sysfolder' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_indexerconfig.sysfolder',
            'description' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_indexerconfig.sysfolder.description',
            'displayCond' => 'FIELD:type:IN:tt_address,news,tt_news',
            'config' => [
                'type' => 'group',
                'allowed' => 'pages',
                'size' => 10,
                'minitems' => 0,
                'maxitems' => 99,
            ],
        ],
        'index_content_with_restrictions' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_indexerconfig.index_content_with_restrictions',
            'description' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_indexerconfig.index_content_with_restrictions.description',
            'displayCond' => 'FIELD:type:=:page',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    [
                        'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_indexerconfig.'
                            . 'index_content_with_restrictions.I.0',
                        'value' => 'yes',
                    ],
                    [
                        'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_indexerconfig.'
                            . 'index_content_with_restrictions.I.1',
                        'value' => 'no',
                    ],
                ],
                'size' => 1,
                'maxitems' => 1,
                'default' => 'no',
            ],
        ],
        'index_news_category_mode' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_indexerconfig.index_news_category_mode',
            'displayCond' => 'FIELD:type:IN:news',
            'onChange' => 'reload',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    [
                        'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_indexerconfig.index_news_category_mode.I.1',
                        'value' => '1',
                    ],
                    [
                        'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_indexerconfig.index_news_category_mode.I.2',
                        'value' => '2',
                    ],
                ],
                'default' => 1,
                'size' => 1,
                'maxitems' => 1,
            ],
        ],
        'index_news_archived' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_indexerconfig.index_news_archived',
            'displayCond' => 'FIELD:type:IN:news',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    [
                        'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_indexerconfig.index_news_archived.I.0',
                        'value' => '0',
                    ],
                    [
                        'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_indexerconfig.index_news_archived.I.1',
                        'value' => '1',
                    ],
                    [
                        'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_indexerconfig.index_news_archived.I.2',
                        'value' => '2',
                    ],
                ],
                'size' => 1,
                'maxitems' => 1,
            ],
        ],
        'index_extnews_category_selection' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_indexerconfig.index_extnews_category_selection',
            'displayCond' => [
                'AND' => [
                    'FIELD:type:=:news',
                    'FIELD:index_news_category_mode:=:2',
                ],
            ],
            'config' => [
                'type' => 'none',
                'behaviour' => [
                    'allowLanguageSynchronization' => true,
                ],
            ],
        ],
        'index_use_page_tags' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_indexerconfig.index_use_page_tags',
            'displayCond' => 'FIELD:type:IN:tt_address,news,tt_news',
            'config' => [
                'type' => 'check',
                'default' => '0',
            ],
        ],
        'directories' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_indexerconfig.directories',
            'description' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_indexerconfig.directories.description',
            'displayCond' => 'FIELD:type:IN:file',
            'config' => [
                'type' => 'text',
                'cols' => 48,
                'rows' => 10,
                'eval' => 'trim',
            ],
        ],
        'fileext' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_indexerconfig.fileext',
            'displayCond' => 'FIELD:type:IN:file,page,tt_content,news,tt_news',
            'config' => [
                'type' => 'input',
                'size' => '30',
                'default' => 'pdf,ppt,doc,xls,docx,xlsx,pptx',
            ],
        ],
        'file_reference_fields' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_indexerconfig.file_reference_fields',
            'description' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_indexerconfig.file_reference_fields.description',
            'displayCond' => 'FIELD:type:IN:page,tt_content',
            'config' => [
                'type' => 'text',
                'cols' => 48,
                'rows' => 4,
                'eval' => 'trim',
                'default' => 'media',
            ],
        ],
        'index_use_page_tags_for_files' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_indexerconfig.index_use_page_tags_for_files',
            'displayCond' => 'FIELD:type:IN:page,tt_content',
            'config' => [
                'type' => 'check',
                'default' => '0',
            ],
        ],
        'index_page_doctypes' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_indexerconfig.index_page_doctypes',
            'description' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_indexerconfig.index_page_doctypes.description',
            'displayCond' => 'FIELD:type:=:page',
            'config' => [
                'type' => 'input',
                'size' => '30',
                'default' => (string)PageRepository::DOKTYPE_DEFAULT,
            ],
        ],
        'content_fields' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_indexerconfig.content_fields',
            'description' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_indexerconfig.content_fields.description',
            'displayCond' => 'FIELD:type:IN:page,tt_content',
            'config' => [
                'type' => 'text',
                'cols' => 48,
                'rows' => 4,
                'eval' => 'trim',
                'default' => 'bodytext,subheader,header_link',
            ],
        ],
        'filteroption' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_indexerconfig.filteroption',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    [
                        'label' => '',
                        'value' => 0,
                    ],
                ],
                'itemsProcFunc' => 'Tpwd\KeSearch\Backend\Filterlist->getListOfAvailableFiltersForTCA',
                'size' => 1,
                'minitems' => 0,
                'maxitems' => 1,
            ],
        ],
        'fal_storage' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_indexerconfig.fal_storage',
            'displayCond' => 'FIELD:type:=:file',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    [
                        'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_indexerconfig.fal_storage.dont_use_fal',
                        'value' => 0,
                    ],
                ],
                'size' => 1,
                'maxitems' => 1,
                'default' => 0,
                'foreign_table' => 'sys_file_storage',
                'allowNonIdValues' => 1,
            ],
        ],
        'contenttypes' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_indexerconfig.contenttypes',
            'description' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_indexerconfig.contenttypes.description',
            'displayCond' => 'FIELD:type:IN:page,tt_content',
            'config' => [
                'type' => 'text',
                'cols' => 48,
                'rows' => 4,
                'eval' => 'trim',
                'default' => 'text,textmedia,textpic,bullets,table,html,header,uploads,shortcut,accordion,tab,carousel,carousel_fullscreen,carousel_small,icon_group,card_group,timeline',
            ],
        ],
        'additional_tables' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_indexerconfig.additional_tables',
            'description' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_indexerconfig.additional_tables.description',
            'displayCond' => 'FIELD:type:IN:page,tt_content',
            'config' => [
                'type' => 'text',
                'cols' => 48,
                'rows' => 4,
                'eval' => 'trim',
                'default' =>
'[accordion]
table = tx_bootstrappackage_accordion_item
referenceFieldName = tt_content
fields[] = header
fields[] = bodytext

[tab]
table = tx_bootstrappackage_tab_item
referenceFieldName = tt_content
fields[] = header
fields[] = bodytext

[carousel]
table = tx_bootstrappackage_carousel_item
referenceFieldName = tt_content
fields[] = header
fields[] = subheader
fields[] = bodytext
fields[] = nav_title
fields[] = link

[carousel_fullscreen]
table = tx_bootstrappackage_carousel_item
referenceFieldName = tt_content
fields[] = header
fields[] = subheader
fields[] = bodytext
fields[] = nav_title
fields[] = link

[carousel_small]
table = tx_bootstrappackage_carousel_item
referenceFieldName = tt_content
fields[] = header
fields[] = subheader
fields[] = bodytext
fields[] = nav_title
fields[] = link

[icon_group]
table = tx_bootstrappackage_icon_group_item
referenceFieldName = tt_content
fields[] = header
fields[] = subheader
fields[] = bodytext
fields[] = link

[card_group]
table = tx_bootstrappackage_card_group_item
referenceFieldName = tt_content
fields[] = header
fields[] = subheader
fields[] = bodytext
fields[] = link
fields[] = link_title

[timeline]
table = tx_bootstrappackage_timeline_item
referenceFieldName = tt_content
fields[] = header
fields[] = bodytext
',
            ],
        ],
        'index_news_files_mode' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_indexerconfig.index_news_files_mode',
            'displayCond' => 'FIELD:type:IN:news,tt_news',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    [
                        'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_indexerconfig.index_news_files_mode.I.0',
                        'value' => '0',
                    ],
                    [
                        'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_indexerconfig.index_news_files_mode.I.1',
                        'value' => '1',
                    ],
                ],
                'size' => 1,
                'maxitems' => 1,
            ],
        ],
    ],
    'types' => [
        '0' => [
            'showitem' => 'hidden,title,type,storagepid,targetpid,
            startingpoints_recursive,single_pages,sysfolder,
                --div--;LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tx_kesearch_indexerconfig.tabs.advanced,
            index_content_with_restrictions,
            index_news_archived,index_news_category_mode,index_extnews_category_selection,
            index_use_page_tags,fal_storage,directories,index_page_doctypes,contenttypes,additional_tables,content_fields,fileext,file_reference_fields,
            index_news_files_mode,
            filteroption,index_use_page_tags_for_files',
        ],
    ],
];

return $txKesearchIndexerconfig;
