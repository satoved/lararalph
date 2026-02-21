<?php

namespace Satoved\Lararalph\Worktree\Steps;

class RunInstallComposer implements WorktreeSetupStep
{
    public function handle(string $worktreePath, string $sourcePath, string $spec): void
    {
        passthru("cd {$worktreePath} && composer install");
    }
}
