<?php

namespace Satoved\Lararalph\Commands;

use Illuminate\Console\Command;
use Satoved\Lararalph\LararalphServiceProvider;

use function Laravel\Prompts\search;

class AgentLoop extends Command
{
    protected $signature = 'ralph:loop
                            {project? : The project name for the PRD (interactive if not provided)}
                            {iterations? : Number of iterations to run (default: 30, ignored with --once)}
                            {--once : Run a single iteration without detaching}
                            {--branch= : Use a custom branch name (default: agent/<project>)}
                            {--worktree : Run in a separate worktree instead of current directory}
                            {--attach : Attach to the screen session after starting}';

    protected $description = 'Start an agent loop session to work through a PRD';

    public function handle()
    {
        $repo = basename(getcwd());
        $project = $this->argument('project');
        $iterations = $this->argument('iterations') ?? 30;
        $once = $this->option('once');
        $branch = $this->option('branch');
        $useWorktree = $this->option('worktree');

        $this->info("Repo: {$repo}");

        // If no project specified, let user choose from available specs
        if (! $project) {
            $project = $this->chooseProject();
            if (! $project) {
                return 1;
            }
        }

        // Resolve the spec path (supports both full folder name and partial match)
        $specPath = $this->resolveSpecPath($project);
        if (! $specPath) {
            $this->error("Spec not found: {$project}");
            $this->info('Available specs in specs/backlog/:');
            foreach ($this->getLocalProjects() as $spec) {
                $this->line("  - {$spec}");
            }

            return 1;
        }

        // Validate PRD.md exists
        $prdFile = $specPath.'/PRD.md';
        if (! file_exists($prdFile)) {
            $this->error("PRD.md not found at: {$prdFile}");

            return 1;
        }

        // Require IMPLEMENTATION_PLAN.md to exist
        $planFile = $specPath.'/IMPLEMENTATION_PLAN.md';
        if (! file_exists($planFile)) {
            $this->error("IMPLEMENTATION_PLAN.md not found at: {$planFile}");
            $this->newLine();
            $this->info("Run 'php artisan ralph:plan {$project}' first to create an implementation plan.");

            return 1;
        }

        // Use the resolved spec folder name for consistency
        $project = basename($specPath);

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

        // Run the agent
        return $this->runCommandAgent($project, $iterations, $once, $repoPath, $workingPath, $repo, $useWorktree, $needsWorktreeSetup);
    }

    protected function runCommandAgent(string $project, int $iterations, bool $once, string $repoPath, string $workingPath, string $repo, bool $useWorktree, bool $needsWorktreeSetup = false): int
    {
        // Build setup commands if this is a new worktree
        $setupCmd = $needsWorktreeSetup
            ? 'composer install && php artisan worktree:setup --skip-composer && '
            : '';

        $settings = config('lararalph.claude.settings', []);
        $settingsJson = json_encode($settings, JSON_UNESCAPED_SLASHES);
        $escapedSettings = escapeshellarg($settingsJson);

        if ($once) {
            $scriptPath = LararalphServiceProvider::binPath('ralph-once.sh');
            $script = "bash {$scriptPath} {$project} {$escapedSettings}";
            $command = "cd {$workingPath} && {$setupCmd}{$script}";

            $this->info('Running single iteration...');
            passthru($command, $exitCode);

            return $exitCode;
        }

        // Screen name includes 'wt' suffix when using worktree to distinguish sessions
        $screenName = "agent-{$project}".($useWorktree ? '-wt' : '');

        $scriptPath = LararalphServiceProvider::binPath('ralph-loop.js');
        $script = "node {$scriptPath} {$project} {$iterations} --settings {$escapedSettings}";
        $innerCmd = "cd {$workingPath} && {$setupCmd}{$script}";

        $command = "screen -dmS {$screenName} zsh -ic '{$innerCmd}'";

        $this->info("Starting detached screen session: {$screenName}");
        $attachCmd = "screen -r {$screenName}";

        passthru($command, $exitCode);

        if ($exitCode !== 0) {
            return $exitCode;
        }

        $this->info('Screen session started successfully.');

        // Track in .live-agents
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

        // Check if branch already exists
        $branchCheck = "cd {$repoPath} && git show-ref --verify --quiet refs/heads/{$branchName} && echo exists";
        $branchExists = trim(shell_exec($branchCheck)) === 'exists';

        if ($branchExists) {
            // Use existing branch
            $commands = implode(' && ', [
                "cd {$repoPath}",
                'git fetch origin master',
                "git worktree add -f {$worktreePath} {$branchName}",
                "cd {$worktreePath}",
                'git reset --hard origin/master',
            ]);
        } else {
            // Create new branch
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

    protected function chooseProject(): ?string
    {
        $this->info('Fetching available specs...');

        $projects = $this->getLocalProjects();

        if (empty($projects)) {
            $this->error('No specs found in specs/backlog/');
            $this->info("Run '/prd' to create a new spec first.");

            return null;
        }

        // Use array_combine so search() returns the project name, not the index
        $projects = array_combine($projects, $projects);

        return search(
            label: 'Select a spec',
            options: fn (string $value) => strlen($value) > 0
                ? array_filter($projects, fn ($p) => str_contains(strtolower($p), strtolower($value)))
                : $projects,
        );
    }

    protected function getLocalProjects(): array
    {
        $specsDir = getcwd().'/specs/backlog';
        if (! is_dir($specsDir)) {
            return [];
        }

        return array_values(array_filter(
            scandir($specsDir),
            fn ($dir) => $dir !== '.' && $dir !== '..' && is_dir("{$specsDir}/{$dir}")
        ));
    }

    protected function resolveSpecPath(string $feature): ?string
    {
        $backlogDir = getcwd().'/specs/backlog';
        $completeDir = getcwd().'/specs/complete';

        // First, try exact match in backlog
        $exactPath = $backlogDir.'/'.$feature;
        if (is_dir($exactPath)) {
            return $exactPath;
        }

        // Try exact match in complete
        $exactPath = $completeDir.'/'.$feature;
        if (is_dir($exactPath)) {
            return $exactPath;
        }

        // Try partial match (search for feature name after date prefix)
        if (is_dir($backlogDir)) {
            foreach (scandir($backlogDir) as $dir) {
                if ($dir === '.' || $dir === '..') {
                    continue;
                }
                // Match if the feature name appears after the date prefix
                if (preg_match('/^\d{4}-\d{2}-\d{2}-(.+)$/', $dir, $matches)) {
                    if ($matches[1] === $feature || str_contains($dir, $feature)) {
                        return $backlogDir.'/'.$dir;
                    }
                }
            }
        }

        if (is_dir($completeDir)) {
            foreach (scandir($completeDir) as $dir) {
                if ($dir === '.' || $dir === '..') {
                    continue;
                }
                if (preg_match('/^\d{4}-\d{2}-\d{2}-(.+)$/', $dir, $matches)) {
                    if ($matches[1] === $feature || str_contains($dir, $feature)) {
                        return $completeDir.'/'.$dir;
                    }
                }
            }
        }

        return null;
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
