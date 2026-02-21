<?php

namespace Satoved\Lararalph\Actions;

use Satoved\Lararalph\Contracts\Spec;
use Satoved\Lararalph\Contracts\SpecRepository;
use Satoved\Lararalph\Exceptions\NoBacklogSpecs;

use function Laravel\Prompts\search;

class ChooseSpec
{
    public function __construct(
        private readonly SpecRepository $specs,
    ) {}

    public function __invoke(string $label = 'Select a spec'): Spec
    {
        $backlogSpecs = $this->specs->getBacklogSpecs();

        if (empty($backlogSpecs)) {
            throw new NoBacklogSpecs;
        }

        $options = array_combine($backlogSpecs, $backlogSpecs);

        $specName = search(
            label: $label,
            options: fn (string $value) => strlen($value) > 0
                ? array_filter($options, fn ($s) => str_contains(strtolower($s), strtolower($value)))
                : $options,
        );

        return $this->specs->resolve($specName);
    }
}
