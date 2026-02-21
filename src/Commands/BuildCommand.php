<?php

namespace Satoved\Lararalph\Commands;

use Illuminate\Console\Command;
use Satoved\Lararalph\AgentRunner;
use Satoved\Lararalph\SpecResolver;

class BuildCommand extends Command
{
    protected $signature = 'ralph:build
                            {spec? : The spec name to build (interactive if not provided)}
                            {--iterations=30 : Number of iterations to run}';

    protected $description = 'Start an agent build session to work through a PRD and implementation plan';

    public function handle(SpecResolver $specs, AgentRunner $runner)
    {
        $spec = $this->argument('spec');

        if (! $spec) {
            $spec = $specs->choose();
            if (! $spec) {
                $this->error('No specs found in specs/backlog/');

                return 1;
            }
        }

        $specPath = $specs->resolve($spec);
        if (! $specPath) {
            $this->error("Spec not found: {$spec}");
            $this->info('Available specs in specs/backlog/:');
            foreach ($specs->getBacklogSpecs() as $s) {
                $this->line("  - {$s}");
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

        return $runner->run($spec, $prompt, (int) $this->option('iterations'));
    }
}
