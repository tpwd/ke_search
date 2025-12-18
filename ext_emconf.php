<?php

/** @noinspection PhpUndefinedVariableInspection */
$EM_CONF[$_EXTKEY] = [
    'title' => 'Faceted Search',
    'description' => 'Faceted fulltext search for TYPO3. Fast, flexible and easy to install and use. Indexes content directly from the databases. Features faceting / filtering, file indexing, images in result lists and respects access restrictions.',
    'category' => 'plugin',
    'version' => '7.0.0',
    'state' => 'stable',
    'author' => 'ke_search Dev Team',
    'author_email' => 'ke_search@tpwd.de',
    'author_company' => 'TPWD AG',
    'constraints' => [
        'depends' => [
            'php' => '8.2.0-8.5.99',
            'typo3' => '13.0.0-14.99.99',
        ],
        'conflicts' => [],
        'suggests' => [
            'dashboard' => '13.4.0-14.99.99',
        ],
    ],
    'autoload' => [
        'psr-4' => ['Tpwd\\KeSearch\\' => 'Classes'],
        'classmap' => ['Classes'],
    ],
];
