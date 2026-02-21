<?php

namespace Satoved\Lararalph\Actions;

use Satoved\Lararalph\Contracts\LoopRunner;
use Satoved\Lararalph\Contracts\Spec;
use Satoved\Lararalph\Enums\LoopRunnerResult;
use Satoved\Lararalph\LararalphServiceProvider;

class RunNodeWrappedLoopRunner implements LoopRunner
{
    public function run(Spec $spec, string $prompt, string $workingDirectory, int $maxIterations = 30): LoopRunnerResult
    {
        $escapedPrompt = escapeshellarg($prompt);
        $scriptPath = LararalphServiceProvider::binPath('ralph-loop.js');

        $command = "cd {$workingDirectory} && RALPH_PROMPT={$escapedPrompt} node {$scriptPath} {$spec->name} {$maxIterations}";

        passthru($command, $exitCode);

        return LoopRunnerResult::from($exitCode);
    }
}
