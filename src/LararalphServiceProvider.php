<?php

namespace Satoved\Lararalph;

use Satoved\Lararalph\Commands\AgentKillCommand;
use Satoved\Lararalph\Commands\AgentLoop;
use Satoved\Lararalph\Commands\AgentPlanCommand;
use Satoved\Lararalph\Commands\AgentStatusCommand;
use Satoved\Lararalph\Commands\AgentTail;
use Satoved\Lararalph\Commands\WorktreeIdeCommand;
use Satoved\Lararalph\Commands\WorktreeSetupCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LararalphServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('lararalph')
            ->hasConfigFile()
            ->hasCommands([
                AgentKillCommand::class,
                AgentLoop::class,
                AgentPlanCommand::class,
                AgentStatusCommand::class,
                AgentTail::class,
                WorktreeIdeCommand::class,
                WorktreeSetupCommand::class,
            ]);
    }

    public function packageBooted(): void
    {
        // Publish specs directory structure to Laravel root
        $this->publishes([
            __DIR__.'/../specs' => base_path('specs'),
        ], 'lararalph-specs');
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
