<?php

return [
    'web_KeSearchBackendModule' => [
        'parent' => 'web',
        'position' => 'bottom',
        'access' => 'user',
        'workspaces' => 'live',
        'iconIdentifier' => 'ext-kesearch-wizard-icon',
        'labels' => 'LLL:EXT:ke_search/Resources/Private/Language/locallang_mod.xlf',
        'routes' => [
            '_default' => [
                'target' => \Tpwd\KeSearch\Controller\BackendModuleController::class,
            ],
        ],
    ],
];
