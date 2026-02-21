<?php

namespace Satoved\Lararalph\Contracts;

interface SearchesSpec
{
    /**
     * @throws \Satoved\Lararalph\Exceptions\NoBacklogSpecs
     */
    public function __invoke(string $label = 'Select a spec'): Spec;
}
