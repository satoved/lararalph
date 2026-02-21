<?php

namespace Satoved\Lararalph\Commands;

use Illuminate\Console\Command;
use Satoved\Lararalph\AgentRunner;
use Satoved\Lararalph\Contracts\SpecResolver;
use Satoved\Lararalph\Worktree\WorktreeCreator;

class PlanCommand extends Command
{
    protected $signature = 'ralph:plan
                            {spec? : The spec name to plan (interactive if not provided)}
                            {--force : Regenerate IMPLEMENTATION_PLAN.md even if it exists}
                            {--create-worktree : Create a git worktree for isolated work}';

    protected $description = 'Create an implementation plan for a PRD by analyzing the codebase';

    public function handle(SpecResolver $specs, AgentRunner $runner, WorktreeCreator $worktreeCreator)
    {
        $specName = $this->argument('spec');
        if (! $specName) {
            $specName = $specs->choose('Select a spec to plan');
            if (! $specName) {
                $this->error('No specs found in specs/backlog/');

                return 1;
            }
        }

        $resolved = $specs->resolve($specName);
        if (! $resolved) {
            $this->error("Spec not found or PRD.md missing: {$specName}");

            return 1;
        }

        if (file_exists($resolved->absolutePlanFilePath) && ! $this->option('force')) {
            $this->error("IMPLEMENTATION_PLAN.md already exists at: {$resolved->absolutePlanFilePath}");
            $this->info('Use --force to regenerate.');

            return 1;
        }

        $cwd = null;

        if ($this->option('create-worktree')) {
            $this->info('Creating worktree...');
            $cwd = $worktreeCreator->create($resolved->name);
            $this->info("Worktree created: {$cwd}");
        }

        $this->info('Creating implementation plan for: '.$resolved->name);
        $this->newLine();

        $prompt = view('lararalph::prompts.plan', [
            'prdFilePath' => $resolved->absolutePrdFilePath,
            'planFilePath' => file_exists($resolved->absolutePlanFilePath) ? $resolved->absolutePlanFilePath : null,
        ])->render();

        $exitCode = $runner->run($resolved->name, $prompt, 1, $cwd);

        if ($exitCode === 0) {
            $this->newLine();
            if (file_exists($resolved->absolutePlanFilePath)) {
                $this->info('Implementation plan created: '.$resolved->absolutePlanFilePath);
            } else {
                $this->warn('Claude completed but IMPLEMENTATION_PLAN.md was not created.');
                $this->info('You may need to run the command again or create it manually.');
            }
        }

        return $exitCode;
    }
}
