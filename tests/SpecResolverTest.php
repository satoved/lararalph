<?php

use Satoved\Lararalph\Contracts\Spec;
use Satoved\Lararalph\Exceptions\SpecFolderDoesNotContainPrdFile;
use Satoved\Lararalph\Exceptions\SpecFolderDoesNotExist;
use Satoved\Lararalph\FileSpecRepository;

beforeEach(function () {
    $this->tempDir = realpath(sys_get_temp_dir()).'/lararalph-spec-test-'.uniqid();
    mkdir($this->tempDir.'/specs/backlog', 0755, true);

    $this->originalBasePath = base_path();
    $tempDir = $this->tempDir;
    (fn () => $this->basePath = $tempDir)->call(app());

    $this->resolver = new FileSpecRepository;
});

afterEach(function () {
    $originalBasePath = $this->originalBasePath;
    (fn () => $this->basePath = $originalBasePath)->call(app());

    // Recursive delete
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($this->tempDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
    }
    rmdir($this->tempDir);
});

describe('getBacklogSpecs', function () {
    it('returns empty array when no specs exist', function () {
        expect($this->resolver->getBacklogSpecs())->toBe([]);
    });

    it('returns spec directory names', function () {
        mkdir($this->tempDir.'/specs/backlog/my-feature', 0755, true);
        mkdir($this->tempDir.'/specs/backlog/another-feature', 0755, true);

        $specs = $this->resolver->getBacklogSpecs();

        expect($specs)->toContain('my-feature', 'another-feature');
    });

    it('ignores files in backlog directory', function () {
        mkdir($this->tempDir.'/specs/backlog/real-spec', 0755, true);
        file_put_contents($this->tempDir.'/specs/backlog/not-a-spec.md', 'content');

        $specs = $this->resolver->getBacklogSpecs();

        expect($specs)->toBe(['real-spec']);
    });

    it('returns empty when backlog directory does not exist', function () {
        rmdir($this->tempDir.'/specs/backlog');

        expect($this->resolver->getBacklogSpecs())->toBe([]);
    });
});

describe('resolve', function () {
    it('resolves exact match in backlog', function () {
        mkdir($this->tempDir.'/specs/backlog/my-feature', 0755, true);
        file_put_contents($this->tempDir.'/specs/backlog/my-feature/PRD.md', '# PRD');

        $result = $this->resolver->resolve('my-feature');

        expect($result)->toBeInstanceOf(Spec::class)
            ->and($result->name)->toBe('my-feature')
            ->and($result->absoluteFolderPath)->toBe($this->tempDir.'/specs/backlog/my-feature')
            ->and($result->absolutePrdFilePath)->toBe($this->tempDir.'/specs/backlog/my-feature/PRD.md')
            ->and($result->absolutePlanFilePath)->toBe($this->tempDir.'/specs/backlog/my-feature/IMPLEMENTATION_PLAN.md');
    });

    it('resolves date-prefixed spec by name suffix', function () {
        mkdir($this->tempDir.'/specs/backlog/2025-01-15-my-feature', 0755, true);
        file_put_contents($this->tempDir.'/specs/backlog/2025-01-15-my-feature/PRD.md', '# PRD');

        $result = $this->resolver->resolve('my-feature');

        expect($result)->toBeInstanceOf(Spec::class)
            ->and($result->name)->toBe('2025-01-15-my-feature')
            ->and($result->absoluteFolderPath)->toBe($this->tempDir.'/specs/backlog/2025-01-15-my-feature');
    });

    it('resolves partial match in date-prefixed spec', function () {
        mkdir($this->tempDir.'/specs/backlog/2025-01-15-my-feature', 0755, true);
        file_put_contents($this->tempDir.'/specs/backlog/2025-01-15-my-feature/PRD.md', '# PRD');

        $result = $this->resolver->resolve('my-feat');

        expect($result)->toBeInstanceOf(Spec::class)
            ->and($result->absoluteFolderPath)->toBe($this->tempDir.'/specs/backlog/2025-01-15-my-feature');
    });

    it('throws SpecFolderDoesNotExist for nonexistent spec', function () {
        expect(fn () => $this->resolver->resolve('nonexistent'))
            ->toThrow(SpecFolderDoesNotExist::class);
    });

    it('throws SpecFolderDoesNotContainPrdFile when PRD is missing', function () {
        mkdir($this->tempDir.'/specs/backlog/no-prd', 0755, true);

        expect(fn () => $this->resolver->resolve('no-prd'))
            ->toThrow(SpecFolderDoesNotContainPrdFile::class);
    });
});

describe('complete', function () {
    it('moves spec directory from backlog to complete', function () {
        mkdir($this->tempDir.'/specs/backlog/my-feature', 0755, true);
        mkdir($this->tempDir.'/specs/complete', 0755, true);
        file_put_contents($this->tempDir.'/specs/backlog/my-feature/PRD.md', '# PRD');

        $spec = $this->resolver->resolve('my-feature');

        $this->resolver->complete($spec);

        expect(is_dir($this->tempDir.'/specs/complete/my-feature'))->toBeTrue()
            ->and(is_dir($this->tempDir.'/specs/backlog/my-feature'))->toBeFalse()
            ->and(file_get_contents($this->tempDir.'/specs/complete/my-feature/PRD.md'))->toBe('# PRD');
    });

    it('creates complete directory if it does not exist', function () {
        mkdir($this->tempDir.'/specs/backlog/my-feature', 0755, true);
        file_put_contents($this->tempDir.'/specs/backlog/my-feature/PRD.md', '# PRD');

        $spec = $this->resolver->resolve('my-feature');

        $this->resolver->complete($spec);

        expect(is_dir($this->tempDir.'/specs/complete'))->toBeTrue()
            ->and(is_dir($this->tempDir.'/specs/complete/my-feature'))->toBeTrue();
    });
});
