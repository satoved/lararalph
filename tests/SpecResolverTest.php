<?php

use Satoved\Lararalph\SpecResolver;

beforeEach(function () {
    $this->tempDir = realpath(sys_get_temp_dir()).'/lararalph-spec-test-'.uniqid();
    mkdir($this->tempDir.'/specs/backlog', 0755, true);
    mkdir($this->tempDir.'/specs/complete', 0755, true);

    $this->originalCwd = getcwd();
    chdir($this->tempDir);

    $this->resolver = new SpecResolver;
});

afterEach(function () {
    chdir($this->originalCwd);

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

        $result = $this->resolver->resolve('my-feature');

        expect($result)->toBe($this->tempDir.'/specs/backlog/my-feature');
    });

    it('resolves exact match in complete', function () {
        mkdir($this->tempDir.'/specs/complete/my-feature', 0755, true);

        $result = $this->resolver->resolve('my-feature');

        expect($result)->toBe($this->tempDir.'/specs/complete/my-feature');
    });

    it('prefers backlog over complete for exact match', function () {
        mkdir($this->tempDir.'/specs/backlog/my-feature', 0755, true);
        mkdir($this->tempDir.'/specs/complete/my-feature', 0755, true);

        $result = $this->resolver->resolve('my-feature');

        expect($result)->toBe($this->tempDir.'/specs/backlog/my-feature');
    });

    it('resolves date-prefixed spec by name suffix', function () {
        mkdir($this->tempDir.'/specs/backlog/2025-01-15-my-feature', 0755, true);

        $result = $this->resolver->resolve('my-feature');

        expect($result)->toBe($this->tempDir.'/specs/backlog/2025-01-15-my-feature');
    });

    it('resolves partial match in date-prefixed spec', function () {
        mkdir($this->tempDir.'/specs/backlog/2025-01-15-my-feature', 0755, true);

        $result = $this->resolver->resolve('my-feat');

        expect($result)->toBe($this->tempDir.'/specs/backlog/2025-01-15-my-feature');
    });

    it('resolves date-prefixed spec in complete directory', function () {
        mkdir($this->tempDir.'/specs/complete/2025-03-20-done-feature', 0755, true);

        $result = $this->resolver->resolve('done-feature');

        expect($result)->toBe($this->tempDir.'/specs/complete/2025-03-20-done-feature');
    });

    it('returns null for nonexistent spec', function () {
        expect($this->resolver->resolve('nonexistent'))->toBeNull();
    });
});

describe('resolveFromCommand', function () {
    it('resolves spec from command argument', function () {
        mkdir($this->tempDir.'/specs/backlog/my-feature', 0755, true);
        file_put_contents($this->tempDir.'/specs/backlog/my-feature/PRD.md', '# PRD');

        $command = Mockery::mock(\Illuminate\Console\Command::class);
        $command->shouldReceive('argument')->with('spec')->andReturn('my-feature');

        $result = $this->resolver->resolveFromCommand($command);

        expect($result)->toBe([
            'specPath' => $this->tempDir.'/specs/backlog/my-feature',
            'prdFile' => $this->tempDir.'/specs/backlog/my-feature/PRD.md',
            'spec' => 'my-feature',
        ]);
    });

    it('returns null and shows error when spec not found', function () {
        $command = Mockery::mock(\Illuminate\Console\Command::class);
        $command->shouldReceive('argument')->with('spec')->andReturn('nonexistent');
        $command->shouldReceive('error')->once()->with('Spec not found: nonexistent');

        $result = $this->resolver->resolveFromCommand($command);

        expect($result)->toBeNull();
    });

    it('returns null and shows error when PRD.md is missing', function () {
        mkdir($this->tempDir.'/specs/backlog/no-prd', 0755, true);

        $command = Mockery::mock(\Illuminate\Console\Command::class);
        $command->shouldReceive('argument')->with('spec')->andReturn('no-prd');
        $command->shouldReceive('error')->once()->with(Mockery::pattern('/PRD\.md not found/'));

        $result = $this->resolver->resolveFromCommand($command);

        expect($result)->toBeNull();
    });

    it('returns null when no specs available and no argument given', function () {
        rmdir($this->tempDir.'/specs/backlog');

        $command = Mockery::mock(\Illuminate\Console\Command::class);
        $command->shouldReceive('argument')->with('spec')->andReturn(null);
        $command->shouldReceive('error')->once()->with('No specs found in specs/backlog/');

        $result = $this->resolver->resolveFromCommand($command);

        expect($result)->toBeNull();
    });
});
