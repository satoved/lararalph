<?php

namespace Satoved\Lararalph\Worktree\Steps;

interface WorktreeSetupStep
{
    public function handle(string $worktreePath, string $sourcePath, string $spec): void;
}
