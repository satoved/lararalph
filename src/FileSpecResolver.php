<?php

namespace Satoved\Lararalph;

use Satoved\Lararalph\Contracts\ResolvedSpec;
use Satoved\Lararalph\Contracts\SpecResolver;

use function Laravel\Prompts\search;

class FileSpecResolver implements SpecResolver
{
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

    public function resolve(string $spec): ?ResolvedSpec
    {
        $specPath = $this->findSpecPath($spec);
        if (! $specPath) {
            return null;
        }

        $prdFile = $specPath.'/PRD.md';
        if (! file_exists($prdFile)) {
            return null;
        }

        return new ResolvedSpec(
            spec: basename($specPath),
            specPath: $specPath,
            prdFile: $prdFile,
        );
    }

    private function findSpecPath(string $spec): ?string
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
