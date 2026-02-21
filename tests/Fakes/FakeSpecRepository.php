<?php

namespace Satoved\Lararalph\Tests\Fakes;

use Satoved\Lararalph\Contracts\Spec;
use Satoved\Lararalph\Contracts\SpecRepository;

class FakeSpecRepository implements SpecRepository
{
    public bool $completed = false;

    public function __construct(
        private ?Spec $spec = null,
        private ?\Throwable $resolveException = null,
        private array $backlogSpecs = [],
    ) {}

    public function getBacklogSpecs(): array
    {
        return $this->backlogSpecs;
    }

    public function resolve(string $spec): Spec
    {
        if ($this->resolveException) {
            throw $this->resolveException;
        }

        return $this->spec;
    }

    public function complete(Spec $spec): void
    {
        $this->completed = true;
    }
}
