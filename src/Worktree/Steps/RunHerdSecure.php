<?php

namespace Satoved\Lararalph\Worktree\Steps;

class RunHerdSecure implements WorktreeSetupStep
{
    public function handle(string $worktreePath, string $sourcePath, string $spec): void
    {
        passthru("cd {$worktreePath} && herd secure");
    }
}
