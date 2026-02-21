<?php

namespace Satoved\Lararalph\Tests\Fakes;

use Closure;
use Satoved\Lararalph\Contracts\LoopRunner;
use Satoved\Lararalph\Contracts\Spec;
use Satoved\Lararalph\Enums\LoopRunnerResult;

class FakeLoopRunner implements LoopRunner
{
    public ?Spec $receivedSpec = null;

    public ?string $receivedPrompt = null;

    public ?int $receivedMaxIterations = null;

    public ?string $receivedWorkingDirectory = null;

    public function __construct(
        private LoopRunnerResult $result = LoopRunnerResult::FullyComplete,
        private ?Closure $callback = null,
    ) {}

    public function run(Spec $spec, string $prompt, string $workingDirectory, int $maxIterations = 30): LoopRunnerResult
    {
        $this->receivedSpec = $spec;
        $this->receivedPrompt = $prompt;
        $this->receivedMaxIterations = $maxIterations;
        $this->receivedWorkingDirectory = $workingDirectory;

        if ($this->callback) {
            ($this->callback)();
        }

        return $this->result;
    }
}
