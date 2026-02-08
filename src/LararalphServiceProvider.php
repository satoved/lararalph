<?php

namespace Satoved\Lararalph;

use Satoved\Lararalph\Commands\BuildCommand;
use Satoved\Lararalph\Commands\AgentKillCommand;
use Satoved\Lararalph\Commands\AgentLoop;
use Satoved\Lararalph\Commands\PlanCommand;
use Satoved\Lararalph\Commands\AgentStatusCommand;
use Satoved\Lararalph\Commands\AgentTail;
use Satoved\Lararalph\Commands\IdeCommand;
use Satoved\Lararalph\Commands\WorktreeCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LararalphServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('lararalph')
            ->hasConfigFile()
            ->hasViews()
            ->hasCommands([
                BuildCommand::class,
                AgentKillCommand::class,
                AgentLoop::class,
                PlanCommand::class,
                AgentStatusCommand::class,
                AgentTail::class,
                IdeCommand::class,
                WorktreeCommand::class,
            ]);
    }

    public function packageBooted(): void
    {
        // Publish specs directory structure to Laravel root
        $this->publishes([
            __DIR__.'/../specs' => base_path('specs'),
        ], 'lararalph-specs');

        // Publish .claude directory (settings, skills, .gitignore)
        $this->publishes([
            __DIR__.'/../.claude' => base_path('.claude'),
        ], 'lararalph-claude');
    }

    /**
     * Get the path to the package's bin directory.
     */
    public static function binPath(string $script = ''): string
    {
        $basePath = dirname(__DIR__).'/bin';

        return $script ? $basePath.'/'.$script : $basePath;
    }
}
