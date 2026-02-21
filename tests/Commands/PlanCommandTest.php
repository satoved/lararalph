<?php

use Satoved\Lararalph\Contracts\LoopRunner;
use Satoved\Lararalph\Contracts\Spec;
use Satoved\Lararalph\Contracts\SpecRepository;
use Satoved\Lararalph\Exceptions\SpecFolderDoesNotContainPrdFile;
use Satoved\Lararalph\Exceptions\SpecFolderDoesNotExist;
use Satoved\Lararalph\LoopRunnerResult;
use Satoved\Lararalph\Tests\Fakes\FakeLoopRunner;
use Satoved\Lararalph\Tests\Fakes\FakeSpecRepository;
use Satoved\Lararalph\Worktree\WorktreeCreator;

beforeEach(function () {
    $this->specDir = sys_get_temp_dir().'/lararalph-test-'.uniqid();
    mkdir($this->specDir, 0755, true);
    file_put_contents($this->specDir.'/PRD.md', '# Test PRD');

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

it('creates a plan successfully when no plan exists', function () {
    $specs = new FakeSpecRepository(spec: $this->resolved);
    $this->app->instance(SpecRepository::class, $specs);

    $runner = new FakeLoopRunner(LoopRunnerResult::FullyComplete);
    $this->app->instance(LoopRunner::class, $runner);

    $this->artisan('ralph:plan', ['spec' => 'test-spec'])
        ->expectsOutputToContain('Creating implementation plan for: test-spec')
        ->assertExitCode(0);

    expect($runner->receivedSpec)->toBeInstanceOf(Spec::class);
    expect($runner->receivedMaxIterations)->toBe(1);
    expect($runner->receivedWorkingDirectory)->toBe(base_path());
});

it('fails when spec folder does not exist', function () {
    $specs = new FakeSpecRepository(resolveException: new SpecFolderDoesNotExist);
    $this->app->instance(SpecRepository::class, $specs);

    $this->artisan('ralph:plan', ['spec' => 'nonexistent'])
        ->expectsOutputToContain('Spec folder not found: nonexistent')
        ->assertExitCode(1);
});

it('fails when spec folder does not contain PRD file', function () {
    $specs = new FakeSpecRepository(resolveException: new SpecFolderDoesNotContainPrdFile);
    $this->app->instance(SpecRepository::class, $specs);

    $this->artisan('ralph:plan', ['spec' => 'no-prd'])
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

    $this->artisan('ralph:plan')
        ->expectsOutputToContain('No specs found in specs/backlog/')
        ->assertExitCode(1);
});

it('fails when plan already exists without force', function () {
    file_put_contents($this->specDir.'/IMPLEMENTATION_PLAN.md', '# Existing Plan');

    $specs = new FakeSpecRepository(spec: $this->resolved);
    $this->app->instance(SpecRepository::class, $specs);

    $this->artisan('ralph:plan', ['spec' => 'test-spec'])
        ->expectsOutputToContain('IMPLEMENTATION_PLAN.md already exists')
        ->expectsOutputToContain('Use --force to regenerate')
        ->assertExitCode(1);
});

it('regenerates plan with force flag', function () {
    file_put_contents($this->specDir.'/IMPLEMENTATION_PLAN.md', '# Existing Plan');

    $specs = new FakeSpecRepository(spec: $this->resolved);
    $this->app->instance(SpecRepository::class, $specs);

    $runner = new FakeLoopRunner(LoopRunnerResult::FullyComplete);
    $this->app->instance(LoopRunner::class, $runner);

    $this->artisan('ralph:plan', ['spec' => 'test-spec', '--force' => true])
        ->expectsOutputToContain('Creating implementation plan for: test-spec')
        ->assertExitCode(0);

    expect($runner->receivedMaxIterations)->toBe(1);
});

it('passes existing plan file path to prompt when forcing regeneration', function () {
    file_put_contents($this->specDir.'/IMPLEMENTATION_PLAN.md', '# Existing Plan');

    $specs = new FakeSpecRepository(spec: $this->resolved);
    $this->app->instance(SpecRepository::class, $specs);

    $runner = new FakeLoopRunner(LoopRunnerResult::FullyComplete);
    $this->app->instance(LoopRunner::class, $runner);

    $this->artisan('ralph:plan', ['spec' => 'test-spec', '--force' => true])
        ->assertExitCode(0);

    expect($runner->receivedPrompt)
        ->toContain('IMPLEMENTATION_PLAN.md')
        ->toContain('Plan only. Do NOT implement anything');
});

it('shows success message when plan file is created', function () {
    $specs = new FakeSpecRepository(spec: $this->resolved);
    $this->app->instance(SpecRepository::class, $specs);

    $specDir = $this->specDir;
    $runner = new FakeLoopRunner(
        result: LoopRunnerResult::FullyComplete,
        callback: function () use ($specDir) {
            file_put_contents($specDir.'/IMPLEMENTATION_PLAN.md', '# New Plan');
        },
    );
    $this->app->instance(LoopRunner::class, $runner);

    $this->artisan('ralph:plan', ['spec' => 'test-spec'])
        ->expectsOutputToContain('Implementation plan created')
        ->assertExitCode(0);
});

it('shows warning when runner succeeds but plan file not created', function () {
    $specs = new FakeSpecRepository(spec: $this->resolved);
    $this->app->instance(SpecRepository::class, $specs);

    $runner = new FakeLoopRunner(LoopRunnerResult::FullyComplete);
    $this->app->instance(LoopRunner::class, $runner);

    $this->artisan('ralph:plan', ['spec' => 'test-spec'])
        ->expectsOutputToContain('IMPLEMENTATION_PLAN.md was not created')
        ->assertExitCode(0);
});

it('propagates runner exit code on failure', function () {
    $specs = new FakeSpecRepository(spec: $this->resolved);
    $this->app->instance(SpecRepository::class, $specs);

    $runner = new FakeLoopRunner(LoopRunnerResult::Error);
    $this->app->instance(LoopRunner::class, $runner);

    $this->artisan('ralph:plan', ['spec' => 'test-spec'])
        ->assertExitCode(1);
});

it('always runs with 1 iteration', function () {
    $specs = new FakeSpecRepository(spec: $this->resolved);
    $this->app->instance(SpecRepository::class, $specs);

    $runner = new FakeLoopRunner(LoopRunnerResult::FullyComplete);
    $this->app->instance(LoopRunner::class, $runner);

    $this->artisan('ralph:plan', ['spec' => 'test-spec'])
        ->assertExitCode(0);

    expect($runner->receivedMaxIterations)->toBe(1);
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

    $this->artisan('ralph:plan', ['spec' => 'test-spec', '--create-worktree' => true])
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

    $this->artisan('ralph:plan', ['spec' => 'test-spec'])
        ->assertExitCode(0);

    expect($runner->receivedWorkingDirectory)->toBe(base_path());
});
