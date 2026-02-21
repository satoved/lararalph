<?php

namespace Satoved\Lararalph;

use Satoved\Lararalph\Contracts\Spec;

class LoopRunner
{
    public function run(Spec $spec, string $prompt, int $iterations = 30, ?string $workingDirectory = null): LoopRunnerResult
    {
        $escapedPrompt = escapeshellarg($prompt);
        $scriptPath = LararalphServiceProvider::binPath('ralph-loop.js');
        $workingDirectory = $workingDirectory ?? base_path();

        $command = "cd {$workingDirectory} && RALPH_PROMPT={$escapedPrompt} node {$scriptPath} {$spec->name} {$iterations}";

        passthru($command, $exitCode);

        return LoopRunnerResult::from($exitCode);
    }
}
