<?php

namespace Satoved\Lararalph\Actions;

use Satoved\Lararalph\Contracts\SpecRepository;

use function Laravel\Prompts\search;

class ChooseSpec
{
    public function __construct(
        private SpecRepository $specs,
    ) {}

    public function __invoke(string $label = 'Select a spec'): ?string
    {
        $backlogSpecs = $this->specs->getBacklogSpecs();

        if (empty($backlogSpecs)) {
            return null;
        }

        $options = array_combine($backlogSpecs, $backlogSpecs);

        return search(
            label: $label,
            options: fn (string $value) => strlen($value) > 0
                ? array_filter($options, fn ($s) => str_contains(strtolower($s), strtolower($value)))
                : $options,
        );
    }
}
