<?php

namespace Satoved\Lararalph\Commands;

use Illuminate\Console\Command;
use Satoved\Lararalph\Contracts\LoopRunner;
use Satoved\Lararalph\Contracts\SearchesSpec;
use Satoved\Lararalph\Contracts\Spec;
use Satoved\Lararalph\Contracts\SpecRepository;
use Satoved\Lararalph\Exceptions\NoBacklogSpecs;
use Satoved\Lararalph\Exceptions\SpecFolderDoesNotContainPrdFile;
use Satoved\Lararalph\Exceptions\SpecFolderDoesNotExist;
use Satoved\Lararalph\Repositories\FileSpecRepository;
use Satoved\Lararalph\Enums\LoopRunnerResult;
use Satoved\Lararalph\Worktree\WorktreeCreator;

class BuildCommand extends Command
{
    protected $signature = 'ralph:build
                            {spec? : The spec name to build (interactive if not provided)}
                            {--iterations=30 : Number of iterations to run}
                            {--create-worktree : Create a git worktree for isolated work}';

    protected $description = 'Start an agent build session to work through a PRD and implementation plan';

    public function handle(SpecRepository $specs, LoopRunner $runner, WorktreeCreator $worktreeCreator, SearchesSpec $chooseSpec)
    {
        try {
            $specName = $this->argument('spec');
            $spec = $specName
                ? $specs->resolve($specName)
                : $chooseSpec('Select a spec to build');
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

        if (! $spec->planFileExists()) {
            $this->error(Spec::PLAN_FILENAME." not found at: {$spec->absolutePlanFilePath}");
            $this->newLine();
            $this->info("Run 'php artisan ralph:plan {$spec->name}' first to create an implementation plan.");

            return self::FAILURE;
        }

        if ($this->option('create-worktree')) {
            $this->info('Creating worktree...');
            $cwd = $worktreeCreator->create($spec->name);
            $this->info("Worktree created: {$cwd}");
        } else {
            $cwd = base_path();
        }

        $this->info("Building: {$spec->name}");
        $this->newLine();

        $prompt = view('lararalph::prompts.build', [
            'prdFilePath' => $spec->absolutePrdFilePath,
            'planFilePath' => $spec->absolutePlanFilePath,
        ])->render();

        $result = $runner->run(
            spec: $spec,
            prompt: $prompt,
            workingDirectory: $cwd,
            maxIterations: (int) $this->option('iterations')
        );

        if ($result === LoopRunnerResult::FullyComplete) {
            $specs->complete($spec);
            $this->info("Spec '{$spec->name}' moved to complete.");
        }

        return $result->value;
    }
}
