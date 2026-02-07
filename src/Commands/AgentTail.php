<?php

namespace Satoved\Lararalph\Commands;

use Illuminate\Console\Command;
use function Laravel\Prompts\search;

class AgentTail extends Command
{
    protected $signature = 'ralph:tail
                            {session? : The session name to tail (interactive if not provided)}';

    protected $description = 'Attach to a running agent loop screen session';

    public function handle()
    {
        $session = $this->argument('session');

        // If no session specified, let user choose from running sessions
        if (!$session) {
            $session = $this->chooseSession();
            if (!$session) {
                return 1;
            }
        }

        $screenName = "agent-{$session}";
        $this->info("Attaching to screen session: {$screenName}");
        $this->info("Press Ctrl+A then D to detach without stopping the session.");
        $this->newLine();

        $command = "screen -r {$screenName}";

        passthru($command, $exitCode);
        return $exitCode;
    }

    protected function chooseSession(): ?string
    {
        $this->info("Fetching running agent sessions...");

        $sessions = $this->getLocalSessions();

        if (empty($sessions)) {
            $this->error("No running agent sessions found");
            return null;
        }

        if (count($sessions) === 1) {
            return array_values($sessions)[0];
        }

        // Use array_combine so search() returns the session name, not the index
        $sessions = array_combine($sessions, $sessions);

        return search(
            label: 'Select a session to attach',
            options: fn (string $value) => strlen($value) > 0
                ? array_filter($sessions, fn ($s) => str_contains(strtolower($s), strtolower($value)))
                : $sessions,
        );
    }

    protected function getLocalSessions(): array
    {
        // Extract everything after "agent-" (handles both "agent-project" and "agent-project-wt")
        $output = shell_exec("screen -ls 2>/dev/null | grep -oE 'agent-[^[:space:]]+' | sed 's/^agent-//'");

        if (!$output) {
            return [];
        }

        return array_filter(array_map('trim', explode("\n", $output)));
    }
}
