<?php
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

ExtensionManagementUtility::addStaticFile(
    'ke_search',
    'Configuration/TypoScript',
    'Faceted Search'
);

// show FlexForm field in plugin configuration
$GLOBALS['TCA']['tt_content']['types']['list']['subtypes_addlist']['ke_search_pi1'] = 'pi_flexform';
$GLOBALS['TCA']['tt_content']['types']['list']['subtypes_addlist']['ke_search_pi2'] = 'pi_flexform';
$GLOBALS['TCA']['tt_content']['types']['list']['subtypes_addlist']['ke_search_pi3'] = 'pi_flexform';

// remove the old "plugin mode" configuration field
$GLOBALS['TCA']['tt_content']['types']['list']['subtypes_excludelist']['ke_search_pi1'] = 'select_key,recursive';
$GLOBALS['TCA']['tt_content']['types']['list']['subtypes_excludelist']['ke_search_pi2'] = 'select_key,recursive,pages';
$GLOBALS['TCA']['tt_content']['types']['list']['subtypes_excludelist']['ke_search_pi3'] = 'select_key,recursive';

// add plugins
if (!is_array($GLOBALS['TCA']['tt_content']['columns']['list_type']['config']['items'] ?? false)) {
    $GLOBALS['TCA']['tt_content']['columns']['list_type']['config']['items'] = [];
}

ExtensionManagementUtility::addTcaSelectItemGroup(
    'tt_content',
    'list_type',
    'ke_search',
    'LLL:EXT:ke_search/Resources/Private/Language/locallang_mod.xlf:mlang_tabs_tab',
    'after:default'
);

ExtensionManagementUtility::addTcaSelectItem(
    'tt_content',
    'list_type',
    [
        'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tt_content.list_type_pi1',
        'value' => 'ke_search_pi1',
        'icon'  => 'ext-kesearch-wizard-icon',
        'group' => 'ke_search',
    ]
);

ExtensionManagementUtility::addTcaSelectItem(
    'tt_content',
    'list_type',
    [
        'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tt_content.list_type_pi2',
        'value' => 'ke_search_pi2',
        'icon'  => 'ext-kesearch-wizard-icon',
        'group' => 'ke_search',
    ]
);

ExtensionManagementUtility::addTcaSelectItem(
    'tt_content',
    'list_type',
    [
        'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tt_content.list_type_pi3',
        'value' => 'ke_search_pi3',
        'icon'  => 'ext-kesearch-wizard-icon',
        'group' => 'ke_search',
    ]
);

// Configure FlexForm field
ExtensionManagementUtility::addPiFlexFormValue(
    'ke_search_pi1',
    'FILE:EXT:ke_search/Configuration/FlexForms/flexform_searchbox.xml'
);

ExtensionManagementUtility::addPiFlexFormValue(
    'ke_search_pi2',
    'FILE:EXT:ke_search/Configuration/FlexForms/flexform_resultlist.xml'
);

ExtensionManagementUtility::addPiFlexFormValue(
    'ke_search_pi3',
    'FILE:EXT:ke_search/Configuration/FlexForms/flexform_searchbox.xml'
);
