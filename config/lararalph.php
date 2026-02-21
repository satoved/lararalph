<?php

use Satoved\Lararalph\Worktree\Setup\CopyEnvFile;
use Satoved\Lararalph\Worktree\Setup\OpenInPHPStorm;
use Satoved\Lararalph\Worktree\Setup\RunHerdSecure;
use Satoved\Lararalph\Worktree\Setup\RunInstallComposer;
use Satoved\Lararalph\Worktree\Setup\RunInstallNpm;

return [
    'worktree_setup' => [
        CopyEnvFile::class,
        RunInstallComposer::class,
        RunInstallNpm::class,
        RunHerdSecure::class,
        OpenInPHPStorm::class,
    ],
];
