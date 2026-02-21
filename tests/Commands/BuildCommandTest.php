<?php

use Satoved\Lararalph\Contracts\LoopRunner;
use Satoved\Lararalph\Contracts\Spec;
use Satoved\Lararalph\Contracts\SpecRepository;
use Satoved\Lararalph\Exceptions\SpecFolderDoesNotContainPrdFile;
use Satoved\Lararalph\Exceptions\SpecFolderDoesNotExist;
use Satoved\Lararalph\Enums\LoopRunnerResult;
use Satoved\Lararalph\Tests\Fakes\FakeLoopRunner;
use Satoved\Lararalph\Tests\Fakes\FakeSpecRepository;
use Satoved\Lararalph\Worktree\WorktreeCreator;

beforeEach(function () {
    $this->specDir = sys_get_temp_dir().'/lararalph-test-'.uniqid();
    mkdir($this->specDir, 0755, true);
    file_put_contents($this->specDir.'/PRD.md', '# Test PRD');
    file_put_contents($this->specDir.'/IMPLEMENTATION_PLAN.md', '# Test Plan');

    $this->resolved = new Spec(
        name: 'test-spec',
        absoluteFolderPath: $this->specDir,
        absolutePrdFilePath: $this->specDir.'/PRD.md',
        absolutePlanFilePath: $this->specDir.'/IMPLEMENTATION_PLAN.md',
    );
});

afterEach(function () {
    if (is_dir($this->specDir)) {
        array_map('unlink', glob($this->specDir.'/*'));
        rmdir($this->specDir);
    }
});

it('runs build successfully with all files present', function () {
    $specs = new FakeSpecRepository(spec: $this->resolved);
    $this->app->instance(SpecRepository::class, $specs);

    $runner = new FakeLoopRunner(LoopRunnerResult::FullyComplete);
    $this->app->instance(LoopRunner::class, $runner);

    $this->artisan('ralph:build', ['spec' => 'test-spec'])
        ->expectsOutputToContain('Building: test-spec')
        ->expectsOutputToContain("Spec 'test-spec' moved to complete.")
        ->assertExitCode(0);

    expect($specs->completed)->toBeTrue();
    expect($runner->receivedSpec)->toBeInstanceOf(Spec::class);
    expect($runner->receivedMaxIterations)->toBe(30);
    expect($runner->receivedWorkingDirectory)->toBe(base_path());
});

it('passes custom iterations to runner', function () {
    $specs = new FakeSpecRepository(spec: $this->resolved);
    $this->app->instance(SpecRepository::class, $specs);

    $runner = new FakeLoopRunner(LoopRunnerResult::FullyComplete);
    $this->app->instance(LoopRunner::class, $runner);

    $this->artisan('ralph:build', ['spec' => 'test-spec', '--iterations' => 10])
        ->assertExitCode(0);

    expect($runner->receivedMaxIterations)->toBe(10);
});

it('fails when spec folder does not exist', function () {
    $specs = new FakeSpecRepository(resolveException: new SpecFolderDoesNotExist);
    $this->app->instance(SpecRepository::class, $specs);

    $this->artisan('ralph:build', ['spec' => 'nonexistent'])
        ->expectsOutputToContain('Spec folder not found: nonexistent')
        ->assertExitCode(1);
});

it('fails when spec folder does not contain PRD file', function () {
    $specs = new FakeSpecRepository(resolveException: new SpecFolderDoesNotContainPrdFile);
    $this->app->instance(SpecRepository::class, $specs);

    $this->artisan('ralph:build', ['spec' => 'no-prd'])
        ->expectsOutputToContain('PRD.md missing for spec: no-prd')
        ->assertExitCode(1);
});

it('fails when no specs available and no argument given', function () {
    $this->app->instance(Satoved\Lararalph\Contracts\SearchesSpec::class, new class implements Satoved\Lararalph\Contracts\SearchesSpec
    {
        public function __invoke(string $label = 'Select a spec'): Satoved\Lararalph\Contracts\Spec
        {
            throw new Satoved\Lararalph\Exceptions\NoBacklogSpecs;
        }
    });

    $this->artisan('ralph:build')
        ->expectsOutputToContain('No specs found in specs/backlog/')
        ->assertExitCode(1);
});

it('fails when implementation plan is missing', function () {
    unlink($this->specDir.'/IMPLEMENTATION_PLAN.md');

    $specs = new FakeSpecRepository(spec: $this->resolved);
    $this->app->instance(SpecRepository::class, $specs);

    $this->artisan('ralph:build', ['spec' => 'test-spec'])
        ->expectsOutputToContain('IMPLEMENTATION_PLAN.md not found')
        ->expectsOutputToContain('ralph:plan test-spec')
        ->assertExitCode(1);
});

it('renders the build prompt with correct file paths', function () {
    $specs = new FakeSpecRepository(spec: $this->resolved);
    $this->app->instance(SpecRepository::class, $specs);

    $runner = new FakeLoopRunner(LoopRunnerResult::FullyComplete);
    $this->app->instance(LoopRunner::class, $runner);

    $this->artisan('ralph:build', ['spec' => 'test-spec'])
        ->assertExitCode(0);

    expect($runner->receivedPrompt)
        ->toContain('ONLY WORK ON A SINGLE TASK')
        ->toContain('IMPLEMENTATION_PLAN.md');
});

it('does not move spec when runner returns exit code 2 (iterations exhausted)', function () {
    $specs = new FakeSpecRepository(spec: $this->resolved);
    $this->app->instance(SpecRepository::class, $specs);

    $runner = new FakeLoopRunner(LoopRunnerResult::MaxIterationsReached);
    $this->app->instance(LoopRunner::class, $runner);

    $this->artisan('ralph:build', ['spec' => 'test-spec'])
        ->assertExitCode(2);

    expect($specs->completed)->toBeFalse();
});

it('does not move spec when runner returns exit code 1 (error)', function () {
    $specs = new FakeSpecRepository(spec: $this->resolved);
    $this->app->instance(SpecRepository::class, $specs);

    $runner = new FakeLoopRunner(LoopRunnerResult::Error);
    $this->app->instance(LoopRunner::class, $runner);

    $this->artisan('ralph:build', ['spec' => 'test-spec'])
        ->assertExitCode(1);

    expect($specs->completed)->toBeFalse();
});

it('creates worktree and passes cwd to runner when --create-worktree is set', function () {
    $specs = new FakeSpecRepository(spec: $this->resolved);
    $this->app->instance(SpecRepository::class, $specs);

    $worktreeCreator = Mockery::mock(WorktreeCreator::class);
    $worktreeCreator->shouldReceive('create')
        ->once()
        ->with('test-spec')
        ->andReturn('/tmp/myapp-test-spec');
    $this->app->instance(WorktreeCreator::class, $worktreeCreator);

    $runner = new FakeLoopRunner(LoopRunnerResult::FullyComplete);
    $this->app->instance(LoopRunner::class, $runner);

    $this->artisan('ralph:build', ['spec' => 'test-spec', '--create-worktree' => true])
        ->expectsOutputToContain('Creating worktree...')
        ->expectsOutputToContain('Worktree created: /tmp/myapp-test-spec')
        ->assertExitCode(0);

    expect($runner->receivedWorkingDirectory)->toBe('/tmp/myapp-test-spec');
});

it('does not create worktree without --create-worktree flag', function () {
    $specs = new FakeSpecRepository(spec: $this->resolved);
    $this->app->instance(SpecRepository::class, $specs);

    $worktreeCreator = Mockery::mock(WorktreeCreator::class);
    $worktreeCreator->shouldNotReceive('create');
    $this->app->instance(WorktreeCreator::class, $worktreeCreator);

    $runner = new FakeLoopRunner(LoopRunnerResult::FullyComplete);
    $this->app->instance(LoopRunner::class, $runner);

    $this->artisan('ralph:build', ['spec' => 'test-spec'])
        ->assertExitCode(0);

    expect($runner->receivedWorkingDirectory)->toBe(base_path());
});
