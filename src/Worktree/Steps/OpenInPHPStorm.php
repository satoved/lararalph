<?php

namespace Satoved\Lararalph\Worktree\Steps;

class OpenInPHPStorm implements WorktreeSetupStep
{
    public function handle(string $worktreePath, string $sourcePath, string $spec): void
    {
        exec("open -na \"PhpStorm.app\" --args {$worktreePath}");
    }
}
