<?php

namespace Satoved\Lararalph;

use Satoved\Lararalph\Contracts\Spec;
use Satoved\Lararalph\Contracts\SpecRepository;

class FileSpecRepository implements SpecRepository
{
    public const string BACKLOG_DIR = 'specs/backlog';

    public const string COMPLETE_DIR = 'specs/complete';

    public function getBacklogSpecs(): array
    {
        $specsDir = base_path(self::BACKLOG_DIR);
        if (! is_dir($specsDir)) {
            return [];
        }

        return array_values(array_filter(
            scandir($specsDir),
            fn ($dir) => $dir !== '.' && $dir !== '..' && is_dir("{$specsDir}/{$dir}")
        ));
    }

    public function resolve(string $spec): ?Spec
    {
        $specPath = $this->findSpecPath($spec);
        if (! $specPath) {
            return null;
        }

        $prdFile = $specPath.'/'.Spec::PRD_FILENAME;
        if (! file_exists($prdFile)) {
            return null;
        }

        return new Spec(
            name: basename($specPath),
            absoluteFolderPath: $specPath,
            absolutePrdFilePath: $prdFile,
            absolutePlanFilePath: $specPath.'/'.Spec::PLAN_FILENAME,
        );
    }

    public function complete(Spec $spec): void
    {
        $completeDir = base_path(self::COMPLETE_DIR);

        if (! is_dir($completeDir)) {
            mkdir($completeDir, 0755, true);
        }

        rename($spec->absoluteFolderPath, $completeDir.'/'.basename($spec->absoluteFolderPath));
    }

    private function findSpecPath(string $spec): ?string
    {
        $backlogDir = base_path(self::BACKLOG_DIR);

        // Try exact match
        $exactPath = $backlogDir.'/'.$spec;
        if (is_dir($exactPath)) {
            return $exactPath;
        }

        // Try partial match (search for spec name after date prefix)
        if (is_dir($backlogDir)) {
            foreach (scandir($backlogDir) as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                if (preg_match('/^\d{4}-\d{2}-\d{2}-(.+)$/', $entry, $matches)) {
                    if ($matches[1] === $spec || str_contains($entry, $spec)) {
                        return $backlogDir.'/'.$entry;
                    }
                }
            }
        }

        return null;
    }
}
