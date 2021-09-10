<?php

return [
    'ke_search:indexing' => [
        'class' => \Tpwd\KeSearch\Command\StartIndexerCommand::class,
        'schedulable' => true,
    ],
    'ke_search:clearindex' => [
        'class' => \Tpwd\KeSearch\Command\ClearIndexCommand::class,
        'schedulable' => true,
    ],
    'ke_search:removelock' => [
        'class' => \Tpwd\KeSearch\Command\RemoveLockCommand::class,
        'schedulable' => true,
    ],
];
