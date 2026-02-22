<?php

namespace Satoved\Lararalph\Worktree;

use RuntimeException;
use Satoved\Lararalph\Contracts\WorktreeSetupStep;
use Satoved\Lararalph\Exceptions\UncommittedChanges;

class WorktreeCreator
{
    public function getWorktreePath(string $spec): string
    {
        $projectName = basename(base_path());

        return dirname(base_path()).'/'.$projectName.'-'.$spec;
    }

    public function getBranchName(string $spec): string
    {
        return "ralph/{$spec}";
    }

    public function create(string $spec): string
    {
        $this->ensureCleanWorkingTree();

        $worktreePath = $this->getWorktreePath($spec);

        if (! is_dir($worktreePath)) {
            $escapedPath = escapeshellarg($worktreePath);
            $escapedBranch = escapeshellarg($this->getBranchName($spec));

            $basePath = escapeshellarg(base_path());
            exec("cd {$basePath} && git worktree add -b {$escapedBranch} {$escapedPath} 2>&1", $output, $exitCode);

            if ($exitCode !== 0) {
                throw new RuntimeException(
                    'Failed to create git worktree: '.implode("\n", $output)
                );
            }
        }

        $sourcePath = base_path();
        $steps = config('lararalph.worktree_setup', []);

        foreach ($steps as $stepClass) {
            $step = app($stepClass);
            assert($step instanceof WorktreeSetupStep);
            $step->handle($worktreePath, $sourcePath, $spec);
        }

        return $worktreePath;
    }

    private function ensureCleanWorkingTree(): void
    {
        $basePath = escapeshellarg(base_path());
        exec("cd {$basePath} && git status --porcelain 2>&1", $output, $exitCode);

        if ($exitCode !== 0) {
            throw new RuntimeException(
                'Failed to check git status: '.implode("\n", $output)
            );
        }

        if (! empty($output)) {
            throw new UncommittedChanges(
                'Cannot create worktree: you have uncommitted changes. Please commit or stash them first.'
            );
        }
    }
}
