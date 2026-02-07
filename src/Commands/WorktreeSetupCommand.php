<?php

namespace Satoved\Lararalph\Commands;

use Illuminate\Console\Command;

use function Laravel\Prompts\spin;

class WorktreeSetupCommand extends Command
{
    protected $signature = 'ralph:setup {--skip-composer : Skip composer install} {--skip-yarn : Skip yarn install}';

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

        // Run composer install
        if (! $this->option('skip-composer')) {
            spin(
                fn () => shell_exec('composer install 2>&1'),
                'Running composer install...'
            );
            $this->info('Composer install complete');
        }

        // Run yarn install
        if (! $this->option('skip-yarn')) {
            spin(
                fn () => shell_exec('yarn install 2>&1'),
                'Running yarn install...'
            );
            $this->info('Yarn install complete');
        }

        // Run valet secure, make sure it's in the sudoers so we don't get prompted for password
        spin(
            fn () => shell_exec('valet secure 2>&1'),
            'Running valet secure...'
        );
        $this->info('Valet secure complete');

        $this->newLine();
        $this->info('Worktree setup complete!');
        $this->line("Your app is configured for: <comment>*.{$currentDir}.test</comment>");

        return 0;
    }
}
