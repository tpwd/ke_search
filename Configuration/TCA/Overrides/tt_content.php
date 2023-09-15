<?php

use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

defined('TYPO3') or die();

$typo3Version = GeneralUtility::makeInstance(Typo3Version::class);
$typo3BranchVersion = (float)$typo3Version->getBranch();

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
ExtensionManagementUtility::addTcaSelectItemGroup(
    'tt_content',
    'list_type',
    'ke_search',
    'LLL:EXT:ke_search/Resources/Private/Language/locallang_mod.xlf:mlang_tabs_tab',
    'after:default'
);

if ($typo3BranchVersion >= 12.3) {
    $listTypeKeSearchPi1ItemArray = [
        'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tt_content.list_type_pi1',
        'value' => 'ke_search_pi1',
        'icon'  => 'EXT:ke_search/Resources/Public/Icons/Extension.svg',
        'group' => 'ke_search',
    ];
    $listTypeKeSearchPi2ItemArray = [
        'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tt_content.list_type_pi2',
        'value' => 'ke_search_pi2',
        'icon'  => 'EXT:ke_search/Resources/Public/Icons/Extension.svg',
        'group' => 'ke_search',
    ];
    $listTypeKeSearchPi3ItemArray = [
        'label' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tt_content.list_type_pi3',
        'value' => 'ke_search_pi3',
        'icon'  => 'EXT:ke_search/Resources/Public/Icons/Extension.svg',
        'group' => 'ke_search',
    ];
} else {
    $listTypeKeSearchPi1ItemArray = [
        'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tt_content.list_type_pi1',
        'ke_search_pi1',
        'EXT:ke_search/Resources/Public/Icons/Extension.svg',
        'ke_search',
    ];
    $listTypeKeSearchPi2ItemArray = [
            'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tt_content.list_type_pi2',
            'ke_search_pi2',
            'EXT:ke_search/Resources/Public/Icons/Extension.svg',
            'ke_search',
        ];
    $listTypeKeSearchPi3ItemArray = [
        'LLL:EXT:ke_search/Resources/Private/Language/locallang_db.xlf:tt_content.list_type_pi3',
        'ke_search_pi3',
        'EXT:ke_search/Resources/Public/Icons/Extension.svg',
        'ke_search',
    ];
}

ExtensionManagementUtility::addTcaSelectItem(
    'tt_content',
    'list_type',
    $listTypeKeSearchPi1ItemArray
);

ExtensionManagementUtility::addTcaSelectItem(
    'tt_content',
    'list_type',
    $listTypeKeSearchPi2ItemArray
);

ExtensionManagementUtility::addTcaSelectItem(
    'tt_content',
    'list_type',
    $listTypeKeSearchPi3ItemArray
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
