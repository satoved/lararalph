<?php

namespace Satoved\Lararalph\Commands;

use Illuminate\Console\Command;
use Satoved\Lararalph\AgentRunner;
use Satoved\Lararalph\SpecResolver;

class PlanCommand extends Command
{
    protected $signature = 'ralph:plan
                            {spec? : The spec name to plan (interactive if not provided)}
                            {--force : Regenerate IMPLEMENTATION_PLAN.md even if it exists}';

    protected $description = 'Create an implementation plan for a PRD by analyzing the codebase';

    public function handle(SpecResolver $specs, AgentRunner $runner)
    {
        $spec = $this->argument('spec');
        $force = $this->option('force');

        if (! $spec) {
            $spec = $specs->choose('Select a spec to plan');
            if (! $spec) {
                $this->error('No specs found in specs/backlog/');

                return 1;
            }
        }

        $specPath = $specs->resolve($spec);
        if (! $specPath) {
            $this->error("Spec not found: {$spec}");
            $this->info("Use '/prd' skill inside Claude to create a new spec first.");

            return 1;
        }

        $prdFile = $specPath.'/PRD.md';
        $planFile = $specPath.'/IMPLEMENTATION_PLAN.md';

        if (! file_exists($prdFile)) {
            $this->error("PRD.md not found at: {$prdFile}");

            return 1;
        }

        if (file_exists($planFile) && ! $force) {
            $this->error("IMPLEMENTATION_PLAN.md already exists at: {$planFile}");
            $this->info('Use --force to regenerate.');

            return 1;
        }

        $spec = basename($specPath);

        $this->info('Creating implementation plan for: '.$spec);
        $this->newLine();

        $prompt = view('lararalph::prompts.plan', [
            'prdFilePath' => $prdFile,
            'planFilePath' => file_exists($planFile) ? $planFile : null,
        ])->render();

        $exitCode = $runner->run($spec, $prompt, 1);

        if ($exitCode === 0) {
            $this->newLine();
            if (file_exists($planFile)) {
                $this->info('Implementation plan created: '.$planFile);
            } else {
                $this->warn('Claude completed but IMPLEMENTATION_PLAN.md was not created.');
                $this->info('You may need to run the command again or create it manually.');
            }
        }

        return $exitCode;
    }
}
