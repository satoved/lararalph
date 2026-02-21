<?php

namespace Satoved\Lararalph\Commands;

use Illuminate\Console\Command;
use Satoved\Lararalph\AgentRunner;
use Satoved\Lararalph\SpecResolver;
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
        $resolved = $specs->resolveFromCommand($this);
        if (! $resolved) {
            return 1;
        }

        $planFile = $resolved['specPath'].'/IMPLEMENTATION_PLAN.md';
        if (! file_exists($planFile)) {
            $this->error("IMPLEMENTATION_PLAN.md not found at: {$planFile}");
            $this->newLine();
            $this->info("Run 'php artisan ralph:plan {$resolved['spec']}' first to create an implementation plan.");

            return 1;
        }

        $cwd = null;

        if ($this->option('create-worktree')) {
            $this->info('Creating worktree...');
            $cwd = $worktreeCreator->create($resolved['spec']);
            $this->info("Worktree created: {$cwd}");
        }

        $this->info("Building: {$resolved['spec']}");
        $this->newLine();

        $prompt = view('lararalph::prompts.build', [
            'prdFilePath' => $resolved['prdFile'],
            'planFilePath' => $planFile,
        ])->render();

        return $runner->run($resolved['spec'], $prompt, (int) $this->option('iterations'), $cwd);
    }
}
