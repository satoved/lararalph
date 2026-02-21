<?php

namespace Satoved\Lararalph\Contracts;

interface SpecResolver
{
    /** List spec directory names in specs/backlog/. */
    public function getBacklogSpecs(): array;

    /**
     * Resolve a spec name to a Spec.
     * Validates PRD.md exists. Returns null if spec not found or PRD missing.
     */
    public function resolve(string $spec): ?Spec;

    /** Interactive spec selection via Laravel Prompts. Returns spec name or null. */
    public function choose(string $label = 'Select a spec'): ?string;
}
