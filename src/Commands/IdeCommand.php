<?php

namespace Satoved\Lararalph\Commands;

use Illuminate\Console\Command;

use function Laravel\Prompts\search;

class IdeCommand extends Command
{
    protected $signature = 'ralph:ide {path?}';

    protected $description = 'Open a git worktree in your IDE';

    public function handle()
    {
        $path = $this->argument('path');

        // Get list of worktrees
        $worktreeOutput = shell_exec('git worktree list --porcelain');
        $worktrees = $this->parseWorktrees($worktreeOutput);

        if (empty($worktrees)) {
            $this->error('No worktrees found');

            return 1;
        }

        // Interactive selection if no path specified
        if (empty($path)) {
            $options = collect($worktrees)
                ->mapWithKeys(fn ($wt) => [$wt['path'] => "{$wt['branch']} ({$wt['path']})"])
                ->toArray();

            $path = search(
                label: 'Select a worktree to open in IDE',
                options: fn (string $search) => collect($options)
                    ->filter(fn ($label) => empty($search) || str_contains(strtolower($label), strtolower($search)))
                    ->toArray(),
                placeholder: 'Type to search...',
                scroll: 15,
            );
        }

        if (empty($path)) {
            $this->error('No worktree selected');

            return 1;
        }

        // Verify the path exists
        if (! is_dir($path)) {
            $this->error("Directory not found: {$path}");

            return 1;
        }

        // Open in IDE
        $ideCommand = config('lararalph.ide', 'open -na "PhpStorm.app" --args {path}');
        $this->info("Opening {$path} in IDE...");
        shell_exec(str_replace('{path}', escapeshellarg($path), $ideCommand));

        return 0;
    }

    private function parseWorktrees(?string $output): array
    {
        if (empty($output)) {
            return [];
        }

        $worktrees = [];
        $current = [];

        foreach (explode("\n", $output) as $line) {
            $line = trim($line);

            if (empty($line)) {
                if (! empty($current['path'])) {
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
            } elseif (str_starts_with($line, 'HEAD ')) {
                $current['head'] = substr($line, 5);
            }
        }

        // Don't forget the last one
        if (! empty($current['path'])) {
            $worktrees[] = $current;
        }

        return $worktrees;
    }
}
