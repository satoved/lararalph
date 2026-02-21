<?php

namespace Satoved\Lararalph\Contracts;

use Satoved\Lararalph\LoopRunnerResult;

interface LoopRunner
{
    public function run(Spec $spec, string $prompt, string $workingDirectory, int $maxIterations = 30): LoopRunnerResult;
}
