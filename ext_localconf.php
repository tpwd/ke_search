<?php

use Tpwd\KeSearch\UserFunction\CustomFieldValidation\FilterOptionTagValidator;

defined('TYPO3') or die();

(function () {
    // Add Searchbox Plugin
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTypoScript(
        'tx_kesearch',
        'setup',
        '
plugin.tx_kesearch_pi1 = USER_INT
plugin.tx_kesearch_pi1.userFunc = Tpwd\KeSearch\Plugins\SearchboxPlugin->main
tt_content.ke_search_pi1 =< lib.contentElement
tt_content.ke_search_pi1 {
    templateName = Generic
    20 =< plugin.tx_kesearch_pi1
}
',
        'defaultContentRendering'
    );

    // add Resultlist Plugin
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTypoScript(
        'tx_kesearch',
        'setup',
        '
plugin.tx_kesearch_pi2 = USER_INT
plugin.tx_kesearch_pi2.userFunc = Tpwd\KeSearch\Plugins\ResultlistPlugin->main
tt_content.ke_search_pi2 =< lib.contentElement
tt_content.ke_search_pi2 {
    templateName = Generic
    20 =< plugin.tx_kesearch_pi2
}
',
        'defaultContentRendering'
    );

    // Add cachable Searchbox Plugin (USER instead of USER_INT)
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTypoScript(
        'tx_kesearch',
        'setup',
        '
plugin.tx_kesearch_pi3 = USER
plugin.tx_kesearch_pi3.userFunc = Tpwd\KeSearch\Plugins\SearchboxPlugin->main
tt_content.ke_search_pi3 =< lib.contentElement
tt_content.ke_search_pi3 {
    templateName = Generic
    20 =< plugin.tx_kesearch_pi3
}
',
        'defaultContentRendering'
    );

    // use hooks for generation of sortdate values
    $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['registerAdditionalFields'][] =
        \Tpwd\KeSearch\Hooks\AdditionalFields::class;

    $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['modifyPagesIndexEntry'][] =
        \Tpwd\KeSearch\Hooks\AdditionalFields::class;

    $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['modifyContentIndexEntry'][] =
        \Tpwd\KeSearch\Hooks\AdditionalFields::class;

    // Custom validators for TCA (eval)
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tce']['formevals'][FilterOptionTagValidator::class] = '';

    // logging
    $extConf = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
        \TYPO3\CMS\Core\Configuration\ExtensionConfiguration::class
    )->get('ke_search');
    $loglevel = strtolower($extConf['loglevel'] ?? 'ERROR');
    $GLOBALS['TYPO3_CONF_VARS']['LOG']['Tpwd']['KeSearch']['writerConfiguration'] = [
        $loglevel => [
            \TYPO3\CMS\Core\Log\Writer\FileWriter::class => [
                'logFileInfix' => 'kesearch',
            ],
        ],
    ];

    // register "after save" hook
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']
    ['ke_search-filter-option'] = \Tpwd\KeSearch\Hooks\FilterOptionHook::class;
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass']
    ['ke_search-filter-option'] = \Tpwd\KeSearch\Hooks\FilterOptionHook::class;

    // Custom aspects for routing
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['routing']['aspects']['KeSearchUrlEncodeMapper'] =
        \Tpwd\KeSearch\Routing\Aspect\KeSearchUrlEncodeMapper::class;
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['routing']['aspects']['KeSearchTagToSlugMapper'] =
        \Tpwd\KeSearch\Routing\Aspect\KeSearchTagToSlugMapper::class;

    // Exclude ke_search parameters from cacheHash calculation
    $GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'] = array_merge(
        $GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'],
        ['^tx_kesearch_pi1']
    );

    // register statistics tables for garbage collection
    // see https://docs.typo3.org/c/typo3/cms-scheduler/main/en-us/Installation/BaseTasks/Index.html#table-garbage-collection-task-example
    if (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('scheduler')) {
        // Once support for TYPO3 13 is dropped, instead of configuring tables via $GLOBALS['TYPO3_CONF_VARS'],
        // tables should now be configured in TCA using the taskOptions configuration
        // https://docs.typo3.org/c/typo3/cms-core/main/en-us/Changelog/14.0/Deprecation-107550-TableGarbageCollectionTaskConfigurationViaGlobals.html
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][\TYPO3\CMS\Scheduler\Task\TableGarbageCollectionTask::class]['options']['tables']['tx_kesearch_stat_search'] = [
            'dateField' => 'tstamp',
            'expirePeriod' => '180', // days
        ];
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][\TYPO3\CMS\Scheduler\Task\TableGarbageCollectionTask::class]['options']['tables']['tx_kesearch_stat_word'] = [
            'dateField' => 'tstamp',
            'expirePeriod' => '180', // days
        ];
    }
})();
