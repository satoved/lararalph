<?php

use Satoved\Lararalph\Worktree\Steps\WorktreeSetupStep;
use Satoved\Lararalph\Worktree\WorktreeCreator;

beforeEach(function () {
    $this->tempDir = realpath(sys_get_temp_dir()).'/lararalph-wt-test-'.uniqid();
    mkdir($this->tempDir, 0755, true);

    // Init a real git repo
    exec("cd {$this->tempDir} && git init && git commit --allow-empty -m 'init' 2>&1");

    $this->originalCwd = getcwd();
    chdir($this->tempDir);
});

afterEach(function () {
    chdir($this->originalCwd);

    // Clean up worktrees before removing
    exec("cd {$this->tempDir} && git worktree prune 2>&1");

    // Remove temp directories
    exec("rm -rf {$this->tempDir}");

    $projectName = basename($this->tempDir);
    $parentDir = dirname($this->tempDir);
    $worktreePattern = $parentDir.'/'.$projectName.'-*';
    exec("rm -rf {$worktreePattern}");
});

it('creates a git worktree at the expected path', function () {
    $creator = new WorktreeCreator;

    config(['lararalph.worktree_setup' => []]);

    $path = $creator->create('test-feature');
    $expectedPath = dirname($this->tempDir).'/'.basename($this->tempDir).'-test-feature';

    expect($path)->toBe($expectedPath);
    expect(is_dir($path))->toBeTrue();
    expect(file_exists($path.'/.git'))->toBeTrue();
});

it('computes worktree path without side effects', function () {
    $creator = new WorktreeCreator;

    $path = $creator->getWorktreePath('my-spec');
    $expectedPath = dirname($this->tempDir).'/'.basename($this->tempDir).'-my-spec';

    expect($path)->toBe($expectedPath);
    expect(is_dir($path))->toBeFalse();
});

it('calls setup steps with correct arguments', function () {
    $step = Mockery::mock(WorktreeSetupStep::class);
    $step->shouldReceive('handle')
        ->once()
        ->withArgs(function ($worktreePath, $sourcePath, $spec) {
            return str_contains($worktreePath, '-test-feature')
                && $sourcePath === getcwd()
                && $spec === 'test-feature';
        });

    $stepClass = get_class($step);
    app()->instance($stepClass, $step);
    config(['lararalph.worktree_setup' => [$stepClass]]);

    $creator = new WorktreeCreator;
    $creator->create('test-feature');
});

it('reuses existing worktree path (idempotent)', function () {
    $creator = new WorktreeCreator;

    config(['lararalph.worktree_setup' => []]);

    $path1 = $creator->create('test-feature');
    $path2 = $creator->create('test-feature');

    expect($path1)->toBe($path2);
    expect(is_dir($path1))->toBeTrue();
});

it('throws RuntimeException on git failure', function () {
    // Point to a non-git directory
    $nonGitDir = sys_get_temp_dir().'/lararalph-nongit-'.uniqid();
    mkdir($nonGitDir, 0755, true);
    chdir($nonGitDir);

    config(['lararalph.worktree_setup' => []]);

    $creator = new WorktreeCreator;

    expect(fn () => $creator->create('test-feature'))
        ->toThrow(RuntimeException::class, 'Failed to create git worktree');

    // Cleanup
    exec("rm -rf {$nonGitDir}");
});
