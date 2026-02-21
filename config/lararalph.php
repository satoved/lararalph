<?php

use Satoved\Lararalph\Worktree\Steps\CopyEnvFile;
use Satoved\Lararalph\Worktree\Steps\OpenInPHPStorm;
use Satoved\Lararalph\Worktree\Steps\RunHerdSecure;
use Satoved\Lararalph\Worktree\Steps\RunInstallComposer;
use Satoved\Lararalph\Worktree\Steps\RunInstallNpm;

return [
    'worktree_setup' => [
        CopyEnvFile::class,
        RunInstallComposer::class,
        RunInstallNpm::class,
        RunHerdSecure::class,
        OpenInPHPStorm::class,
    ],
];
