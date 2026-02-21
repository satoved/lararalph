<?php

namespace Satoved\Lararalph\Contracts;

interface SpecResolver
{
    /** List spec directory names in the backlog directory. */
    public function getBacklogSpecs(): array;

    /**
     * Resolve a spec name to a Spec.
     * Validates the PRD file exists. Returns null if spec not found or PRD missing.
     */
    public function resolve(string $spec): ?Spec;

    /** Interactive spec selection via Laravel Prompts. Returns spec name or null. */
    public function choose(string $label = 'Select a spec'): ?string;
}
