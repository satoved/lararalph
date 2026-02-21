<?php

namespace Satoved\Lararalph\Worktree\Steps;

class RunInstallNpm implements WorktreeSetupStep
{
    public function handle(string $worktreePath, string $sourcePath, string $spec): void
    {
        passthru("cd {$worktreePath} && npm install");
    }
}
