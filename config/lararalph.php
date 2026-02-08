<?php

return [
    'worktree' => [
        'setup_commands' => [
            'composer install',
            'npm install', // or 'yarn install'...
            'herd secure', // or 'valet secure'...
        ],
    ],
];
