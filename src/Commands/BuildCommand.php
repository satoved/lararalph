<?php

namespace Satoved\Lararalph\Commands;

use Illuminate\Console\Command;
use Satoved\Lararalph\Commands\Concerns\ResolvesSpecs;

class BuildCommand extends Command
{
    use ResolvesSpecs;

    protected $signature = 'ralph:build
                            {spec? : The spec name to build (interactive if not provided)}
                            {iterations? : Number of iterations to run (default: 30, ignored with --once)}
                            {--once : Run a single iteration without detaching}
                            {--branch= : Use a custom branch name (default: agent/<spec>)}
                            {--worktree : Run in a separate worktree instead of current directory}
                            {--attach : Attach to the screen session after starting}';

    protected $description = 'Start an agent build session to work through a PRD and implementation plan';

    public function handle()
    {
        $spec = $this->argument('spec');

        if (! $spec) {
            $spec = $this->chooseSpec();
            if (! $spec) {
                return 1;
            }
        }

        $specPath = $this->resolveSpecPath($spec);
        if (! $specPath) {
            $this->error("Spec not found: {$spec}");
            $this->info('Available specs in specs/backlog/:');
            foreach ($this->getBacklogSpecs() as $spec) {
                $this->line("  - {$spec}");
            }

            return 1;
        }

        $prdFile = $specPath.'/PRD.md';
        $planFile = $specPath.'/IMPLEMENTATION_PLAN.md';

        if (! file_exists($prdFile)) {
            $this->error("PRD.md not found at: {$prdFile}");

            return 1;
        }

        if (! file_exists($planFile)) {
            $this->error("IMPLEMENTATION_PLAN.md not found at: {$planFile}");
            $this->newLine();
            $this->info("Run 'php artisan ralph:plan {$spec}' first to create an implementation plan.");

            return 1;
        }

        $spec = basename($specPath);

        $this->info("Building: {$spec}");
        $this->newLine();

        $prompt = view('lararalph::prompts.build', [
            'prdFilePath' => $prdFile,
            'planFilePath' => $planFile,
        ])->render();

        $args = [
            'spec' => $spec,
            '--prompt' => $prompt,
        ];

        if ($this->argument('iterations')) {
            $args['iterations'] = $this->argument('iterations');
        }

        if ($this->option('once')) {
            $args['--once'] = true;
        }

        if ($this->option('branch')) {
            $args['--branch'] = $this->option('branch');
        }

        if ($this->option('worktree')) {
            $args['--worktree'] = true;
        }

        if ($this->option('attach')) {
            $args['--attach'] = true;
        }

        return $this->call('ralph:loop', $args);
    }
}
