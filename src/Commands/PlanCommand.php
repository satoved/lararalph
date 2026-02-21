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
        $resolved = $specs->resolveFromCommand($this, 'Select a spec to plan');
        if (! $resolved) {
            return 1;
        }

        $planFile = $resolved['specPath'].'/IMPLEMENTATION_PLAN.md';

        if (file_exists($planFile) && ! $this->option('force')) {
            $this->error("IMPLEMENTATION_PLAN.md already exists at: {$planFile}");
            $this->info('Use --force to regenerate.');

            return 1;
        }

        $this->info('Creating implementation plan for: '.$resolved['spec']);
        $this->newLine();

        $prompt = view('lararalph::prompts.plan', [
            'prdFilePath' => $resolved['prdFile'],
            'planFilePath' => file_exists($planFile) ? $planFile : null,
        ])->render();

        $exitCode = $runner->run($resolved['spec'], $prompt, 1);

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
