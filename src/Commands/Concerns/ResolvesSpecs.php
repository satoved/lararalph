<?php

namespace Satoved\Lararalph\Commands\Concerns;

use function Laravel\Prompts\search;

trait ResolvesSpecs
{
    protected function chooseSpec(string $label = 'Select a spec'): ?string
    {
        $this->info('Fetching available specs...');

        $specs = $this->getBacklogSpecs();

        if (empty($specs)) {
            $this->error('No specs found in specs/backlog/');
            $this->info("Run '/prd' to create a new spec first.");

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

    protected function getBacklogSpecs(): array
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

    protected function resolveSpecPath(string $feature): ?string
    {
        $backlogDir = getcwd().'/specs/backlog';
        $completeDir = getcwd().'/specs/complete';

        // First, try exact match in backlog
        $exactPath = $backlogDir.'/'.$feature;
        if (is_dir($exactPath)) {
            return $exactPath;
        }

        // Try exact match in complete
        $exactPath = $completeDir.'/'.$feature;
        if (is_dir($exactPath)) {
            return $exactPath;
        }

        // Try partial match (search for feature name after date prefix)
        if (is_dir($backlogDir)) {
            foreach (scandir($backlogDir) as $dir) {
                if ($dir === '.' || $dir === '..') {
                    continue;
                }
                if (preg_match('/^\d{4}-\d{2}-\d{2}-(.+)$/', $dir, $matches)) {
                    if ($matches[1] === $feature || str_contains($dir, $feature)) {
                        return $backlogDir.'/'.$dir;
                    }
                }
            }
        }

        if (is_dir($completeDir)) {
            foreach (scandir($completeDir) as $dir) {
                if ($dir === '.' || $dir === '..') {
                    continue;
                }
                if (preg_match('/^\d{4}-\d{2}-\d{2}-(.+)$/', $dir, $matches)) {
                    if ($matches[1] === $feature || str_contains($dir, $feature)) {
                        return $completeDir.'/'.$dir;
                    }
                }
            }
        }

        return null;
    }
}
