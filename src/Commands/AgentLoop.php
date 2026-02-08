<?php

namespace Satoved\Lararalph\Commands;

use Illuminate\Console\Command;
use Satoved\Lararalph\LararalphServiceProvider;

class AgentLoop extends Command
{
    protected $signature = 'ralph:loop
                            {project : The project name (used for screen session naming and logging)}
                            {iterations? : Number of iterations to run (default: 30, ignored with --once)}
                            {--once : Run a single iteration without detaching}
                            {--prompt= : The prompt to send to Claude (required)}
                            {--branch= : Use a custom branch name (default: agent/<project>)}
                            {--worktree : Run in a separate worktree instead of current directory}
                            {--attach : Attach to the screen session after starting}';

    protected $description = 'Low-level executor: runs the agent loop with a given prompt';

    public function handle()
    {
        $prompt = $this->option('prompt');

        if (! $prompt) {
            $this->error('The --prompt option is required.');

            return 1;
        }

        $project = $this->argument('project');
        $iterations = $this->argument('iterations') ?? 30;
        $once = $this->option('once');
        $branch = $this->option('branch');
        $useWorktree = $this->option('worktree');

        $repoPath = getcwd();

        $needsWorktreeSetup = false;
        if ($useWorktree) {
            $branchName = 'agent/'.($branch ?: $project);
            $workingPath = getenv('HOME')."/www/example-{$project}";

            $this->info("Setting up worktree for branch: {$branchName}");
            $setupResult = $this->setupWorktree($repoPath, $workingPath, $branchName);
            if ($setupResult === null) {
                return 1;
            }
            $needsWorktreeSetup = $setupResult;
            $this->info("Worktree ready at: {$workingPath}");
            $this->newLine();
        } else {
            $workingPath = $repoPath;
        }

        return $this->runCommandAgent($project, $iterations, $once, $repoPath, $workingPath, $useWorktree, $needsWorktreeSetup, $prompt);
    }

    protected function runCommandAgent(string $project, int $iterations, bool $once, string $repoPath, string $workingPath, bool $useWorktree, bool $needsWorktreeSetup, string $prompt): int
    {
        $setupCmd = $needsWorktreeSetup
            ? 'composer install && php artisan worktree:setup --skip-composer && '
            : '';

        $escapedPrompt = escapeshellarg($prompt);

        if ($once) {
            $scriptPath = LararalphServiceProvider::binPath('ralph-loop.js');
            $script = "RALPH_PROMPT={$escapedPrompt} node {$scriptPath} {$project} 1";
            $command = "cd {$workingPath} && {$setupCmd}{$script}";

            $this->info('Running single iteration...');
            passthru($command, $exitCode);

            return $exitCode;
        }

        $screenName = "agent-{$project}".($useWorktree ? '-wt' : '');

        $scriptPath = LararalphServiceProvider::binPath('ralph-loop.js');
        $script = "RALPH_PROMPT={$escapedPrompt} node {$scriptPath} {$project} {$iterations}";
        $innerCmd = "cd {$workingPath} && {$setupCmd}{$script}";

        $command = "screen -dmS {$screenName} zsh -ic '{$innerCmd}'";

        $this->info("Starting detached screen session: {$screenName}");
        $attachCmd = "screen -r {$screenName}";

        passthru($command, $exitCode);

        if ($exitCode !== 0) {
            return $exitCode;
        }

        $this->info('Screen session started successfully.');

        $this->trackAgent($screenName, $project, $workingPath);

        if ($this->option('attach')) {
            $this->newLine();
            $this->info('Attaching to screen session...');
            passthru($attachCmd, $exitCode);

            return $exitCode;
        }

        $this->info("Attach with: {$attachCmd}");
        $this->newLine();

        return $exitCode;
    }

    /**
     * Setup worktree and return whether it needs setup (new worktree).
     * Returns null on failure, true if new (needs setup), false if existing.
     */
    protected function setupWorktree(string $repoPath, string $worktreePath, string $branchName): ?bool
    {
        $worktreeExists = is_dir($worktreePath);

        if ($worktreeExists) {
            $this->info('Worktree already exists, skipping setup...');

            return false;
        }

        $this->info('Creating new worktree...');

        $branchCheck = "cd {$repoPath} && git show-ref --verify --quiet refs/heads/{$branchName} && echo exists";
        $branchExists = trim(shell_exec($branchCheck)) === 'exists';

        if ($branchExists) {
            $commands = implode(' && ', [
                "cd {$repoPath}",
                'git fetch origin master',
                "git worktree add -f {$worktreePath} {$branchName}",
                "cd {$worktreePath}",
                'git reset --hard origin/master',
            ]);
        } else {
            $commands = implode(' && ', [
                "cd {$repoPath}",
                'git fetch origin master',
                "git worktree add -b {$branchName} {$worktreePath} origin/master",
            ]);
        }

        passthru($commands, $exitCode);

        if ($exitCode !== 0) {
            $this->error("Failed to create worktree. Exit code: {$exitCode}");

            return null;
        }

        return true;
    }

    protected function trackAgent(string $screenName, string $project, string $workingPath): void
    {
        $liveAgentsFile = base_path('.claude/.live-agents');
        $agents = [];

        if (file_exists($liveAgentsFile)) {
            $agents = json_decode(file_get_contents($liveAgentsFile), true) ?? [];
        }

        $agents[$screenName] = [
            'project' => $project,
            'workingPath' => $workingPath,
            'startedAt' => now()->toIso8601String(),
        ];

        if (! is_dir(dirname($liveAgentsFile))) {
            mkdir(dirname($liveAgentsFile), 0755, true);
        }

        file_put_contents($liveAgentsFile, json_encode($agents, JSON_PRETTY_PRINT));
    }
}
