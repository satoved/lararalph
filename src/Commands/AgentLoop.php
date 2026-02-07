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
                            {--remote : Run on claudebox instead of locally}
                            {--attach : Attach to the screen session after starting}';

    protected $description = 'Start an agent loop session to work through a PRD';

    protected bool $remote = false;
    protected string $host = 'claudebox';

    public function handle()
    {
        $repo = basename(getcwd());
        $project = $this->argument('project');
        $iterations = $this->argument('iterations') ?? 30;
        $once = $this->option('once');
        $branch = $this->option('branch');
        $useWorktree = $this->option('worktree');
        $this->remote = $this->option('remote');

        $this->info("Repo: {$repo}");

        // Sync remote if needed
        if ($this->remote) {
            $this->info("Syncing claudebox with latest changes...");
            $syncExitCode = $this->call('claudebox:sync', ['--branch' => 'master']);
            if ($syncExitCode !== 0) {
                $this->error("Failed to sync claudebox. Exit code: {$syncExitCode}");
                return $syncExitCode;
            }
            $this->newLine();
        }

        // If no project specified, let user choose from available PRDs
        if (!$project) {
            $project = $this->chooseProject();
            if (!$project) {
                return 1;
            }
        }

        // Validate project files exist (local only)
        if (!$this->remote) {
            $prdFile = getcwd() . "/prd/backlog/{$project}/project.md";
            if (!file_exists($prdFile)) {
                $this->error("PRD file not found: prd/backlog/{$project}/project.md");
                return 1;
            }
        }

        $repoPath = $this->remote ? "~/www/{$repo}" : getcwd();

        $needsWorktreeSetup = false;
        if ($useWorktree) {
            $branchName = "agent/" . ($branch ?: $project);
            $workingPath = $this->remote ? "~/www /example-{$project}" : getenv('HOME') . "/www/example-{$project}";

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

        if ($once) {
            $scriptPath = $this->remote
                ? "{$repoPath}/bin/ralph-once.sh"
                : LararalphServiceProvider::binPath('ralph-once.sh');
            $script = "bash {$scriptPath} {$project}";
            $command = "cd {$workingPath} && {$setupCmd}{$script}";

            $this->info("Running single iteration...");
            return $this->execCommand($command, tty: true);
        }

        // Screen name includes 'wt' suffix when using worktree to distinguish sessions
        $screenName = $this->remote
            ? "agent-{$repo}-{$project}" . ($useWorktree ? '-wt' : '')
            : "agent-{$project}" . ($useWorktree ? '-wt' : '');

        $scriptPath = $this->remote
            ? "{$repoPath}/bin/ralph-loop.js"
            : LararalphServiceProvider::binPath('ralph-loop.js');
        $script = "node {$scriptPath} {$project} {$iterations}";
        $innerCmd = "cd {$workingPath} && {$setupCmd}{$script}";

        $command = $this->remote
            ? "screen -dmS {$screenName} zsh -ic \\\"{$innerCmd}\\\""
            : "screen -dmS {$screenName} zsh -ic '{$innerCmd}'";

        $this->info("Starting detached screen session: {$screenName}");
        $attachCmd = $this->remote
            ? "ssh -t {$this->host} 'screen -r {$screenName}'"
            : "screen -r {$screenName}";

        $exitCode = $this->execCommand($command);

        if ($exitCode !== 0) {
            return $exitCode;
        }

        $this->info("Screen session started successfully.");

        // Track in .live-agents
        $this->trackAgent($screenName, $project, $workingPath);

        if ($this->option('attach')) {
            $this->newLine();
            $this->info("Attaching to screen session...");
            return $this->execCommand($attachCmd, tty: true);
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
        $worktreeExists = $this->remote
            ? trim(shell_exec("ssh {$this->host} 'test -d {$worktreePath} && echo exists'")) === 'exists'
            : is_dir($worktreePath);

        if ($worktreeExists) {
            $this->info("Worktree already exists, skipping setup...");
            return false;
        }

        $this->info("Creating new worktree...");

        // Check if branch already exists
        $branchCheck = "cd {$repoPath} && git show-ref --verify --quiet refs/heads/{$branchName} && echo exists";
        $branchExists = $this->remote
            ? trim(shell_exec("ssh {$this->host} 'zsh -lc \"{$branchCheck}\"'")) === 'exists'
            : trim(shell_exec($branchCheck)) === 'exists';

        if ($branchExists) {
            // Use existing branch
            $commands = implode(' && ', [
                "cd {$repoPath}",
                "git fetch origin master",
                "git worktree add -f {$worktreePath} {$branchName}",
                "cd {$worktreePath}",
                "git reset --hard origin/master",
            ]);
        } else {
            // Create new branch
            $commands = implode(' && ', [
                "cd {$repoPath}",
                "git fetch origin master",
                "git worktree add -b {$branchName} {$worktreePath} origin/master",
            ]);
        }

        $exitCode = $this->execCommand($commands);

        if ($exitCode !== 0) {
            $this->error("Failed to create worktree. Exit code: {$exitCode}");
            return null;
        }

        return true;
    }

    protected function chooseProject(): ?string
    {
        $this->info($this->remote ? "Fetching available PRDs from claudebox..." : "Fetching available PRDs...");

        $projects = $this->remote
            ? $this->getRemoteProjects()
            : $this->getLocalProjects();

        if (empty($projects)) {
            $this->error("No PRDs found");
            return null;
        }

        // Use array_combine so search() returns the project name, not the index
        $projects = array_combine($projects, $projects);

        return search(
            label: 'Select a PRD',
            options: fn (string $value) => strlen($value) > 0
                ? array_filter($projects, fn ($p) => str_contains(strtolower($p), strtolower($value)))
                : $projects,
        );
    }

    protected function getLocalProjects(): array
    {
        $prdDir = getcwd() . '/prd/backlog';
        if (!is_dir($prdDir)) {
            return [];
        }

        return array_values(array_filter(
            scandir($prdDir),
            fn ($dir) => $dir !== '.' && $dir !== '..' && is_dir("{$prdDir}/{$dir}")
        ));
    }

    protected function getRemoteProjects(): array
    {
        $repo = basename(getcwd());
        $repoPath = "~/www/{$repo}";

        $output = shell_exec("ssh {$this->host} 'ls -1 {$repoPath}/prd/backlog 2>/dev/null'");

        if (!$output) {
            return [];
        }

        return array_filter(array_map('trim', explode("\n", $output)));
    }

    protected function execCommand(string $command, bool $tty = false): int
    {
        if ($this->remote) {
            $ttyFlag = $tty ? '-t' : '';
            $command = "ssh {$ttyFlag} {$this->host} 'zsh -lc \"{$command}\"'";
        }

        passthru($command, $exitCode);
        return $exitCode;
    }

    protected function trackAgent(string $screenName, string $project, string $workingPath): void
    {
        $liveAgentsFile = base_path('.live-agents');
        $agents = [];

        if (file_exists($liveAgentsFile)) {
            $agents = json_decode(file_get_contents($liveAgentsFile), true) ?? [];
        }

        $agents[$screenName] = [
            'project' => $project,
            'workingPath' => $workingPath,
            'remote' => $this->remote,
            'host' => $this->remote ? $this->host : null,
            'startedAt' => now()->toIso8601String(),
        ];

        file_put_contents($liveAgentsFile, json_encode($agents, JSON_PRETTY_PRINT));
    }
}
