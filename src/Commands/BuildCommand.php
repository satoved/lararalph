<?php

namespace Satoved\Lararalph\Commands;

use Illuminate\Console\Command;
use Satoved\Lararalph\AgentRunner;
use Satoved\Lararalph\Contracts\Spec;
use Satoved\Lararalph\Contracts\SpecResolver;
use Satoved\Lararalph\FileSpecResolver;
use Satoved\Lararalph\Worktree\WorktreeCreator;

class BuildCommand extends Command
{
    protected $signature = 'ralph:build
                            {spec? : The spec name to build (interactive if not provided)}
                            {--iterations=30 : Number of iterations to run}
                            {--create-worktree : Create a git worktree for isolated work}';

    protected $description = 'Start an agent build session to work through a PRD and implementation plan';

    public function handle(SpecResolver $specs, AgentRunner $runner, WorktreeCreator $worktreeCreator)
    {
        $specName = $this->argument('spec');
        if (! $specName) {
            $specName = $specs->choose();
            if (! $specName) {
                $this->error('No specs found in '.FileSpecResolver::BACKLOG_DIR.'/');

                return 1;
            }
        }

        $resolved = $specs->resolve($specName);
        if (! $resolved) {
            $this->error('Spec not found or '.Spec::PRD_FILENAME." missing: {$specName}");

            return 1;
        }

        if (! file_exists($resolved->absolutePlanFilePath)) {
            $this->error(Spec::PLAN_FILENAME." not found at: {$resolved->absolutePlanFilePath}");
            $this->newLine();
            $this->info("Run 'php artisan ralph:plan {$resolved->name}' first to create an implementation plan.");

            return 1;
        }

        $cwd = null;

        if ($this->option('create-worktree')) {
            $this->info('Creating worktree...');
            $cwd = $worktreeCreator->create($resolved->name);
            $this->info("Worktree created: {$cwd}");
        }

        $this->info("Building: {$resolved->name}");
        $this->newLine();

        $prompt = view('lararalph::prompts.build', [
            'prdFilePath' => $resolved->absolutePrdFilePath,
            'planFilePath' => $resolved->absolutePlanFilePath,
        ])->render();

        return $runner->run($resolved->name, $prompt, (int) $this->option('iterations'), $cwd);
    }
}
