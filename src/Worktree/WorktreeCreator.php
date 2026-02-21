<?php

namespace Satoved\Lararalph\Worktree;

use RuntimeException;
use Satoved\Lararalph\Worktree\Steps\WorktreeSetupStep;

class WorktreeCreator
{
    public function getWorktreePath(string $spec): string
    {
        $projectName = basename(getcwd());

        return dirname(getcwd()).'/'.$projectName.'-'.$spec;
    }

    public function create(string $spec): string
    {
        $worktreePath = $this->getWorktreePath($spec);

        if (! is_dir($worktreePath)) {
            $escapedPath = escapeshellarg($worktreePath);
            $escapedBranch = escapeshellarg($spec);

            exec("git worktree add -b {$escapedBranch} {$escapedPath} 2>&1", $output, $exitCode);

            if ($exitCode !== 0) {
                throw new RuntimeException(
                    'Failed to create git worktree: '.implode("\n", $output)
                );
            }
        }

        $sourcePath = getcwd();
        $steps = config('lararalph.worktree_setup', []);

        foreach ($steps as $stepClass) {
            $step = app($stepClass);
            assert($step instanceof WorktreeSetupStep);
            $step->handle($worktreePath, $sourcePath, $spec);
        }

        return $worktreePath;
    }
}
