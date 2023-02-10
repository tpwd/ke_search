<?php
return [
    'web_KeSearchBackendModule' => [
        'parent' => 'web',
        'position' => ['after' => '*'],
        'access' => 'user',
        'workspaces' => 'live',
        'iconIdentifier' => 'ext-kesearch-wizard-icon',
        'navigationComponent' => 'TYPO3/CMS/Backend/PageTree/PageTreeElement',
        'labels' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_mod.xlf',
        'extensionName' => 'KeSearch',
        'controllerActions' => [
            \Tpwd\KeSearch\Controller\BackendModuleController::class => [
                'startIndexing',
                'indexedContent',
                'indexTableInformation',
                'searchwordStatistics',
                'clearSearchIndex',
                'lastIndexingReport',
                'alert'
            ],
        ],
    ],
];
