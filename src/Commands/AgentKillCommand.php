<?php

namespace Satoved\Lararalph\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\search;

class AgentKillCommand extends Command
{
    protected $signature = 'ralph:kill {screen? : Screen name to kill directly}';

    protected $description = 'Kill an active agent session';

    public function handle()
    {
        $liveAgentsFile = base_path('.claude/.live-agents');

        if (! file_exists($liveAgentsFile)) {
            $this->info('No agents tracked.');
            return 0;
        }

        $agents = json_decode(file_get_contents($liveAgentsFile), true) ?? [];

        if (empty($agents)) {
            $this->info('No agents tracked.');
            return 0;
        }

        // Get running screen sessions
        $localScreens = $this->getLocalScreens();

        // Build list of running agents
        $runningAgents = [];
        foreach ($agents as $screenName => $agent) {
            $isRunning = in_array($screenName, $localScreens);

            if ($isRunning) {
                $startedAt = Carbon::parse($agent['startedAt']);
                $duration = $startedAt->diffForHumans(syntax: Carbon::DIFF_ABSOLUTE);

                $runningAgents[$screenName] = [
                    ...$agent,
                    'screenName' => $screenName,
                    'duration' => $duration,
                ];
            }
        }

        if (empty($runningAgents)) {
            $this->info('No running agents found.');
            return 0;
        }

        // If screen name provided directly, use it
        $screenName = $this->argument('screen');

        if ($screenName && ! isset($runningAgents[$screenName])) {
            $this->error("Agent '{$screenName}' not found or not running.");
            return 1;
        }

        // Interactive selection if not provided
        if (! $screenName) {
            $options = [];
            foreach ($runningAgents as $name => $agent) {
                $options[$name] = sprintf(
                    '%s (%s)',
                    $agent['project'],
                    $agent['duration']
                );
            }

            $screenName = search(
                label: 'Select agent to kill',
                options: fn (string $value) => collect($options)
                    ->filter(fn ($label) => empty($value) || str_contains(strtolower($label), strtolower($value)))
                    ->toArray(),
                scroll: 15,
            );
        }

        if (empty($screenName)) {
            $this->info('No agent selected.');
            return 0;
        }

        $agent = $runningAgents[$screenName];

        if (! confirm("Kill agent '{$agent['project']}'?", default: false)) {
            $this->info('Cancelled.');
            return 0;
        }

        // Kill the screen session
        $this->info('Killing screen session...');
        shell_exec("screen -S {$screenName} -X quit 2>/dev/null");

        // Remove from tracking
        unset($agents[$screenName]);

        if (empty($agents)) {
            unlink($liveAgentsFile);
        } else {
            file_put_contents($liveAgentsFile, json_encode($agents, JSON_PRETTY_PRINT));
        }

        $this->info("Agent '{$agent['project']}' killed and removed from tracking.");

        return 0;
    }

    protected function getLocalScreens(): array
    {
        $output = shell_exec('screen -ls 2>/dev/null') ?? '';

        return $this->parseScreenOutput($output);
    }

    protected function parseScreenOutput(string $output): array
    {
        $screens = [];
        $lines = explode("\n", $output);

        foreach ($lines as $line) {
            if (preg_match('/^\s*\d+\.([^\s]+)\s+\((Detached|Attached)\)/', $line, $matches)) {
                $screens[] = $matches[1];
            }
        }

        return $screens;
    }
}
