<?php

namespace Satoved\Lararalph;

use Satoved\Lararalph\Contracts\LoopRunner;
use Satoved\Lararalph\Contracts\Spec;

class NodeWrapperLoopRunner implements LoopRunner
{
    public function run(Spec $spec, string $prompt, string $workingDirectory, int $maxIterations = 30): LoopRunnerResult
    {
        $escapedPrompt = escapeshellarg($prompt);
        $scriptPath = LararalphServiceProvider::binPath('ralph-loop.js');
        $workingDirectory = $workingDirectory ?? base_path();

        $command = "cd {$workingDirectory} && RALPH_PROMPT={$escapedPrompt} node {$scriptPath} {$spec->name} {$maxIterations}";

        passthru($command, $exitCode);

        return LoopRunnerResult::from($exitCode);
    }
}
