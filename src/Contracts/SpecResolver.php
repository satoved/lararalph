<?php

namespace Satoved\Lararalph\Contracts;

interface SpecResolver
{
    /** List spec directory names in specs/backlog/. */
    public function getBacklogSpecs(): array;

    /**
     * Resolve a spec name to a ResolvedSpec.
     * Validates PRD.md exists. Returns null if spec not found or PRD missing.
     */
    public function resolve(string $spec): ?ResolvedSpec;

    /** Interactive spec selection via Laravel Prompts. Returns spec name or null. */
    public function choose(string $label = 'Select a spec'): ?string;
}
