<?php

return [
    'ide' => 'open -na "PhpStorm.app" --args {path}', // or 'code {path}' for VSCode

    'worktree' => [
        'setup_commands' => array_filter(
            explode(
                ',',
                env('LARARALPH_WORKTREE_SETUP_COMMANDS', 'composer install,npm install,herd secure') // or 'composer install,yarn install,valet secure'
            )
        ),
    ],
];
