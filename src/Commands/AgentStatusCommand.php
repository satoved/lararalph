<?php

namespace Satoved\Lararalph\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;

class AgentStatusCommand extends Command
{
    protected $signature = 'ralph:status
                            {--clean : Remove completed agents from tracking}';

    protected $description = 'Show status of all tracked agent sessions';

    public function handle()
    {
        $liveAgentsFile = base_path('.claude/.live-agents');

        if (! file_exists($liveAgentsFile)) {
            $this->info('No agents tracked yet.');

            return 0;
        }

        $agents = json_decode(file_get_contents($liveAgentsFile), true) ?? [];

        if (empty($agents)) {
            $this->info('No agents tracked.');

            return 0;
        }

        // Get running screen sessions
        $localScreens = $this->getLocalScreens();

        $rows = [];
        $completed = [];

        foreach ($agents as $screenName => $agent) {
            $isRunning = in_array($screenName, $localScreens);

            $startedAt = Carbon::parse($agent['startedAt']);
            $duration = $startedAt->locale('en')->diffForHumans(syntax: Carbon::DIFF_ABSOLUTE).' ago';

            $status = $isRunning ? '<fg=yellow>running</>' : '<fg=green>complete</>';

            $rows[] = [
                $agent['project'],
                $status,
                $duration,
                $screenName,
            ];

            if (! $isRunning) {
                $completed[] = $screenName;
            }
        }

        $this->table(
            ['Project', 'Status', 'Started', 'Screen'],
            $rows
        );

        // Summary
        $runningCount = count($agents) - count($completed);
        $this->newLine();
        $this->line(sprintf(
            '<fg=yellow>%d running</>, <fg=green>%d complete</>, %d total',
            $runningCount,
            count($completed),
            count($agents)
        ));

        // Clean up completed agents if requested
        if ($this->option('clean') && ! empty($completed)) {
            foreach ($completed as $screenName) {
                unset($agents[$screenName]);
            }

            if (empty($agents)) {
                unlink($liveAgentsFile);
            } else {
                file_put_contents($liveAgentsFile, json_encode($agents, JSON_PRETTY_PRINT));
            }

            $this->newLine();
            $this->info(sprintf('Removed %d completed agent(s) from tracking.', count($completed)));
        } elseif (! empty($completed)) {
            $this->line('<fg=gray>Use --clean to remove completed agents from tracking.</>');
        }

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
            // Match lines like: "	12345.agent-project-wt	(Detached)" or "(Attached)"
            if (preg_match('/^\s*\d+\.([^\s]+)\s+\((Detached|Attached)\)/', $line, $matches)) {
                $screens[] = $matches[1];
            }
        }

        return $screens;
    }
}
