<?php

namespace Satoved\Lararalph;

use Illuminate\Console\Command;

use function Laravel\Prompts\search;

class SpecResolver
{
    /**
     * Resolve a spec from a command's argument, with interactive selection fallback.
     *
     * Returns [specPath, prdFile, spec] or null on failure (errors written to command).
     */
    public function resolveFromCommand(Command $command, string $label = 'Select a spec'): ?array
    {
        $spec = $command->argument('spec');

        if (! $spec) {
            $spec = $this->choose($label);
            if (! $spec) {
                $command->error('No specs found in specs/backlog/');

                return null;
            }
        }

        $specPath = $this->resolve($spec);
        if (! $specPath) {
            $command->error("Spec not found: {$spec}");

            return null;
        }

        $prdFile = $specPath.'/PRD.md';
        if (! file_exists($prdFile)) {
            $command->error("PRD.md not found at: {$prdFile}");

            return null;
        }

        return [
            'specPath' => $specPath,
            'prdFile' => $prdFile,
            'spec' => basename($specPath),
        ];
    }
    public function choose(string $label = 'Select a spec'): ?string
    {
        $specs = $this->getBacklogSpecs();

        if (empty($specs)) {
            return null;
        }

        $specs = array_combine($specs, $specs);

        return search(
            label: $label,
            options: fn (string $value) => strlen($value) > 0
                ? array_filter($specs, fn ($s) => str_contains(strtolower($s), strtolower($value)))
                : $specs,
        );
    }

    public function getBacklogSpecs(): array
    {
        $specsDir = getcwd().'/specs/backlog';
        if (! is_dir($specsDir)) {
            return [];
        }

        return array_values(array_filter(
            scandir($specsDir),
            fn ($dir) => $dir !== '.' && $dir !== '..' && is_dir("{$specsDir}/{$dir}")
        ));
    }

    public function resolve(string $spec): ?string
    {
        $backlogDir = getcwd().'/specs/backlog';
        $completeDir = getcwd().'/specs/complete';

        // First, try exact match in backlog
        $exactPath = $backlogDir.'/'.$spec;
        if (is_dir($exactPath)) {
            return $exactPath;
        }

        // Try exact match in complete
        $exactPath = $completeDir.'/'.$spec;
        if (is_dir($exactPath)) {
            return $exactPath;
        }

        // Try partial match (search for spec name after date prefix)
        foreach ([$backlogDir, $completeDir] as $dir) {
            if (! is_dir($dir)) {
                continue;
            }

            foreach (scandir($dir) as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                if (preg_match('/^\d{4}-\d{2}-\d{2}-(.+)$/', $entry, $matches)) {
                    if ($matches[1] === $spec || str_contains($entry, $spec)) {
                        return $dir.'/'.$entry;
                    }
                }
            }
        }

        return null;
    }
}
