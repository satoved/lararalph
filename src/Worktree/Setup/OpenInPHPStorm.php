<?php

namespace Satoved\Lararalph\Worktree\Setup;

use Satoved\Lararalph\Contracts\WorktreeSetupStep;

class OpenInPHPStorm implements WorktreeSetupStep
{
    public function handle(string $worktreePath, string $sourcePath, string $spec): void
    {
        exec("open -na \"PhpStorm.app\" --args {$worktreePath}");
    }
}
