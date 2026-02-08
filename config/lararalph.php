<?php

return [
    'ide' => 'open -na "PhpStorm.app" --args {path}', // or 'code {path}' for VSCode

    'worktree' => [
        'setup_commands' => [
            'composer install',
            'npm install', // or 'yarn install'...
            'herd secure', // or 'valet secure'...
        ],
    ],
];
