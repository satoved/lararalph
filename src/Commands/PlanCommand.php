<?php

namespace Satoved\Lararalph\Commands;

use Illuminate\Console\Command;
use Satoved\Lararalph\Contracts\LoopRunner;
use Satoved\Lararalph\Contracts\SearchesSpec;
use Satoved\Lararalph\Contracts\Spec;
use Satoved\Lararalph\Contracts\SpecRepository;
use Satoved\Lararalph\Enums\LoopRunnerResult;
use Satoved\Lararalph\Exceptions\NoBacklogSpecs;
use Satoved\Lararalph\Exceptions\SpecFolderDoesNotContainPrdFile;
use Satoved\Lararalph\Exceptions\SpecFolderDoesNotExist;
use Satoved\Lararalph\Exceptions\UncommittedChanges;
use Satoved\Lararalph\Repositories\FileSpecRepository;
use Satoved\Lararalph\Worktree\WorktreeCreator;

class PlanCommand extends Command
{
    protected $signature = 'ralph:plan
                            {spec? : The spec name to plan (interactive if not provided)}
                            {--force : Regenerate the implementation plan even if it exists}
                            {--create-worktree : Create a git worktree for isolated work}';

    protected $description = 'Create an implementation plan for a PRD by analyzing the codebase';

    public function handle(SpecRepository $specs, LoopRunner $runner, WorktreeCreator $worktreeCreator, SearchesSpec $chooseSpec)
    {
        try {
            $specName = $this->argument('spec');
            $spec = $specName
                ? $specs->resolve($specName)
                : $chooseSpec('Select a spec to plan');
        } catch (NoBacklogSpecs) {
            $this->error('No specs found in '.FileSpecRepository::BACKLOG_DIR.'/');

            return self::FAILURE;
        } catch (SpecFolderDoesNotExist) {
            $this->error("Spec folder not found: {$specName}");

            return self::FAILURE;
        } catch (SpecFolderDoesNotContainPrdFile) {
            $this->error(Spec::PRD_FILENAME." missing for spec: {$specName}");

            return self::FAILURE;
        }

        if ($spec->planFileExists() && ! $this->option('force')) {
            $this->error(Spec::PLAN_FILENAME." already exists at: {$spec->absolutePlanFilePath}");
            $this->info('Use --force to regenerate.');

            return self::FAILURE;
        }

        try {
            if ($this->option('create-worktree')) {
                $this->info('Creating worktree...');
                $cwd = $worktreeCreator->create($spec->name);
                $this->info("Worktree created: {$cwd}");
            } else {
                $cwd = base_path();
            }
        } catch (UncommittedChanges $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Creating implementation plan for: '.$spec->name);
        $this->newLine();

        $prompt = view('lararalph::prompts.plan', [
            'prdFilePath' => $spec->absolutePrdFilePath,
            'planFilePath' => $spec->planFileExists() ? $spec->absolutePlanFilePath : null,
        ])->render();

        $result = $runner->run(
            spec: $spec,
            prompt: $prompt,
            workingDirectory: $cwd,
            maxIterations: 1
        );

        if ($result === LoopRunnerResult::FullyComplete) {
            $this->newLine();
            if ($spec->planFileExists()) {
                $this->info('Implementation plan created: '.$spec->absolutePlanFilePath);
            } else {
                $this->warn('Claude completed but '.Spec::PLAN_FILENAME.' was not created.');
                $this->info('You may need to run the command again or create it manually.');
            }
        }

        return $result->value;
    }
}
