<?php

return [
    'kesearch_indexerstatus_getstatus' => [
        'path' => '/ke-search/indexer-status/get-status',
        'target' => \Tpwd\KeSearch\Controller\IndexerStatusController::class . '::getStatusAction',
    ],
];
