<?php

namespace Satoved\Lararalph;

class AgentRunner
{
    public function run(string $spec, string $prompt, int $iterations = 30, ?string $cwd = null): int
    {
        $escapedPrompt = escapeshellarg($prompt);
        $scriptPath = LararalphServiceProvider::binPath('ralph-loop.js');
        $cwd = $cwd ?? getcwd();

        $command = "cd {$cwd} && RALPH_PROMPT={$escapedPrompt} node {$scriptPath} {$spec} {$iterations}";

        passthru($command, $exitCode);

        return $exitCode;
    }
}
