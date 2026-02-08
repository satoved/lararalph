<?php

namespace Satoved\Lararalph\Commands;

use Illuminate\Console\Command;

use function Laravel\Prompts\spin;

class WorktreeSetupCommand extends Command
{
    protected $signature = 'ralph:setup';

    protected $description = 'Setup a git worktree with .env, composer, and yarn';

    public function handle()
    {
        $currentDir = basename(base_path());
        $mainEnvPath = dirname(base_path()) . '/example/.env';

        // Check if we're in the main directory
        if ($currentDir === 'example') {
            $this->error('This command should be run from a worktree, not the main directory');
            return 1;
        }

        // Check if main .env exists
        if (! file_exists($mainEnvPath)) {
            $this->error("Main .env not found at: {$mainEnvPath}");
            return 1;
        }

        // Copy .env
        $this->info('Copying .env from main directory...');
        $envContent = file_get_contents($mainEnvPath);

        // Replace example.test with current directory name
        $envContent = str_replace('example.test', "{$currentDir}.test", $envContent);

        file_put_contents(base_path('.env'), $envContent);
        $this->info("Updated APP_URL to use {$currentDir}.test");

        // Run configured setup commands
        $commands = config('lararalph.worktree.setup_commands', []);
        foreach ($commands as $command) {
            spin(
                fn () => shell_exec($command . ' 2>&1'),
                "Running {$command}..."
            );
            $this->info("Running {$command} complete");
        }

        $this->newLine();
        $this->info('Worktree setup complete!');
        $this->line("Your app is configured for: <comment>*.{$currentDir}.test</comment>");

        return 0;
    }
}
