<?php

namespace Satoved\Lararalph\Commands;

use Illuminate\Console\Command;

use function Laravel\Prompts\multiselect;

class WorktreeRemoveCommand extends Command
{
    protected $signature = 'ralph:worktree:remove';

    protected $description = 'List and remove git worktrees';

    public function handle()
    {
        $repoPath = getcwd();

        $output = shell_exec("cd {$repoPath} && git worktree list --porcelain 2>&1");
        if (! $output) {
            $this->error('Failed to list worktrees.');

            return 1;
        }

        $worktrees = $this->parseWorktrees($output, $repoPath);

        if (empty($worktrees)) {
            $this->info('No removable worktrees found (only the main worktree exists).');

            return 0;
        }

        $choices = [];
        foreach ($worktrees as $wt) {
            $label = "{$wt['path']} [{$wt['branch']}]";
            $choices[$wt['path']] = $label;
        }

        $selected = multiselect(
            label: 'Select worktrees to remove',
            options: $choices,
        );

        if (empty($selected)) {
            $this->info('No worktrees selected.');

            return 0;
        }

        foreach ($selected as $path) {
            $this->info("Removing worktree: {$path}");
            passthru("cd {$repoPath} && git worktree remove --force " . escapeshellarg($path), $exitCode);

            if ($exitCode !== 0) {
                $this->error("Failed to remove worktree: {$path}");
            } else {
                $this->info("Removed: {$path}");
            }
        }

        passthru("cd {$repoPath} && git worktree prune");

        $this->newLine();
        $this->info('Done.');

        return 0;
    }

    protected function parseWorktrees(string $output, string $mainRepoPath): array
    {
        $worktrees = [];
        $current = [];

        foreach (explode("\n", $output) as $line) {
            $line = trim($line);

            if ($line === '') {
                if (! empty($current) && ($current['path'] ?? '') !== $mainRepoPath) {
                    $worktrees[] = $current;
                }
                $current = [];

                continue;
            }

            if (str_starts_with($line, 'worktree ')) {
                $current['path'] = substr($line, 9);
            } elseif (str_starts_with($line, 'branch ')) {
                $current['branch'] = str_replace('refs/heads/', '', substr($line, 7));
            } elseif ($line === 'bare') {
                $current['bare'] = true;
            } elseif ($line === 'detached') {
                $current['branch'] = 'detached';
            }
        }

        // Handle last entry
        if (! empty($current) && ($current['path'] ?? '') !== $mainRepoPath) {
            $worktrees[] = $current;
        }

        return $worktrees;
    }
}