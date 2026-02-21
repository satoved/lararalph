<?php

namespace Satoved\Lararalph\Commands;

use Illuminate\Console\Command;
use Satoved\Lararalph\AgentRunner;
use Satoved\Lararalph\Contracts\SearchesSpec;
use Satoved\Lararalph\Contracts\Spec;
use Satoved\Lararalph\Contracts\SpecRepository;
use Satoved\Lararalph\Exceptions\NoBacklogSpecs;
use Satoved\Lararalph\Exceptions\SpecFolderDoesNotContainPrdFile;
use Satoved\Lararalph\Exceptions\SpecFolderDoesNotExist;
use Satoved\Lararalph\FileSpecRepository;
use Satoved\Lararalph\Worktree\WorktreeCreator;

class BuildCommand extends Command
{
    protected $signature = 'ralph:build
                            {spec? : The spec name to build (interactive if not provided)}
                            {--iterations=30 : Number of iterations to run}
                            {--create-worktree : Create a git worktree for isolated work}';

    protected $description = 'Start an agent build session to work through a PRD and implementation plan';

    public function handle(SpecRepository $specs, AgentRunner $runner, WorktreeCreator $worktreeCreator, SearchesSpec $chooseSpec)
    {
        try {
            $specName = $this->argument('spec');
            $resolved = $specName
                ? $specs->resolve($specName)
                : $chooseSpec();
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

        if (! $resolved->planFileExists()) {
            $this->error(Spec::PLAN_FILENAME." not found at: {$resolved->absolutePlanFilePath}");
            $this->newLine();
            $this->info("Run 'php artisan ralph:plan {$resolved->name}' first to create an implementation plan.");

            return self::FAILURE;
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

        $exitCode = $runner->run($resolved->name, $prompt, (int) $this->option('iterations'), $cwd);

        if ($exitCode === self::SUCCESS) {
            $specs->complete($resolved);
            $this->info("Spec '{$resolved->name}' moved to complete.");
        }

        return $exitCode;
    }
}
