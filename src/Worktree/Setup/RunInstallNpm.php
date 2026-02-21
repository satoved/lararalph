<?php

namespace Satoved\Lararalph\Worktree\Setup;

use Satoved\Lararalph\Contracts\WorktreeSetupStep;

class RunInstallNpm implements WorktreeSetupStep
{
    public function handle(string $worktreePath, string $sourcePath, string $spec): void
    {
        passthru("cd {$worktreePath} && npm install");
    }
}
