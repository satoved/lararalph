<?php

namespace Satoved\Lararalph\Contracts;

interface WorktreeSetupStep
{
    public function handle(string $worktreePath, string $sourcePath, string $spec): void;
}
