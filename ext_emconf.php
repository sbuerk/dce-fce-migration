<?php

$EM_CONF['dce_fce_migration'] = [
    'title' => 'DCE to FCE Migration Toolchain',
    'description' => 'Contains simple but specialised toolchain to migrate DCE to FCE',
    'category' => 'misc',
    'author' => 'Stefan Bürk',
    'author_email' => 'stefan@buerk.tech',
    'state' => 'stable',
    'clearCacheOnLoad' => true,
    'version' => '0.0.3',
    'constraints' => [
        'depends' => [
            'typo3' => '10.5.0 - 10.5.99',
        ],
        'conflicts' => [
        ],
        'suggests' => [
        ],
    ],
];
