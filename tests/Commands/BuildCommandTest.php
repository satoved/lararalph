<?php

use Satoved\Lararalph\AgentRunner;
use Satoved\Lararalph\Contracts\Spec;
use Satoved\Lararalph\Contracts\SpecResolver;
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
    $specs = Mockery::mock(SpecResolver::class);
    $specs->shouldReceive('resolve')->once()->with('test-spec')->andReturn($this->resolved);
    $this->app->instance(SpecResolver::class, $specs);

    $runner = Mockery::mock(AgentRunner::class);
    $runner->shouldReceive('run')
        ->once()
        ->with('test-spec', Mockery::type('string'), 30, null)
        ->andReturn(0);
    $this->app->instance(AgentRunner::class, $runner);

    $this->artisan('ralph:build', ['spec' => 'test-spec'])
        ->expectsOutputToContain('Building: test-spec')
        ->assertExitCode(0);
});

it('passes custom iterations to runner', function () {
    $specs = Mockery::mock(SpecResolver::class);
    $specs->shouldReceive('resolve')->once()->andReturn($this->resolved);
    $this->app->instance(SpecResolver::class, $specs);

    $runner = Mockery::mock(AgentRunner::class);
    $runner->shouldReceive('run')
        ->once()
        ->with('test-spec', Mockery::type('string'), 10, null)
        ->andReturn(0);
    $this->app->instance(AgentRunner::class, $runner);

    $this->artisan('ralph:build', ['spec' => 'test-spec', '--iterations' => 10])
        ->assertExitCode(0);
});

it('fails when spec resolution returns null', function () {
    $specs = Mockery::mock(SpecResolver::class);
    $specs->shouldReceive('resolve')->once()->with('nonexistent')->andReturn(null);
    $this->app->instance(SpecResolver::class, $specs);

    $this->artisan('ralph:build', ['spec' => 'nonexistent'])
        ->expectsOutputToContain('Spec not found or PRD.md missing: nonexistent')
        ->assertExitCode(1);
});

it('fails when no specs available and no argument given', function () {
    $specs = Mockery::mock(SpecResolver::class);
    $specs->shouldReceive('choose')->once()->andReturn(null);
    $this->app->instance(SpecResolver::class, $specs);

    $this->artisan('ralph:build')
        ->expectsOutputToContain('No specs found in specs/backlog/')
        ->assertExitCode(1);
});

it('fails when implementation plan is missing', function () {
    unlink($this->specDir.'/IMPLEMENTATION_PLAN.md');

    $specs = Mockery::mock(SpecResolver::class);
    $specs->shouldReceive('resolve')->once()->andReturn($this->resolved);
    $this->app->instance(SpecResolver::class, $specs);

    $this->artisan('ralph:build', ['spec' => 'test-spec'])
        ->expectsOutputToContain('IMPLEMENTATION_PLAN.md not found')
        ->expectsOutputToContain('ralph:plan test-spec')
        ->assertExitCode(1);
});

it('renders the build prompt with correct file paths', function () {
    $specs = Mockery::mock(SpecResolver::class);
    $specs->shouldReceive('resolve')->once()->andReturn($this->resolved);
    $this->app->instance(SpecResolver::class, $specs);

    $runner = Mockery::mock(AgentRunner::class);
    $runner->shouldReceive('run')
        ->once()
        ->withArgs(function ($spec, $prompt, $iterations) {
            return str_contains($prompt, 'ONLY WORK ON A SINGLE TASK')
                && str_contains($prompt, 'IMPLEMENTATION_PLAN.md');
        })
        ->andReturn(0);
    $this->app->instance(AgentRunner::class, $runner);

    $this->artisan('ralph:build', ['spec' => 'test-spec'])
        ->assertExitCode(0);
});

it('propagates runner exit code', function () {
    $specs = Mockery::mock(SpecResolver::class);
    $specs->shouldReceive('resolve')->once()->andReturn($this->resolved);
    $this->app->instance(SpecResolver::class, $specs);

    $runner = Mockery::mock(AgentRunner::class);
    $runner->shouldReceive('run')->once()->andReturn(2);
    $this->app->instance(AgentRunner::class, $runner);

    $this->artisan('ralph:build', ['spec' => 'test-spec'])
        ->assertExitCode(2);
});

it('creates worktree and passes cwd to runner when --create-worktree is set', function () {
    $specs = Mockery::mock(SpecResolver::class);
    $specs->shouldReceive('resolve')->once()->andReturn($this->resolved);
    $this->app->instance(SpecResolver::class, $specs);

    $worktreeCreator = Mockery::mock(WorktreeCreator::class);
    $worktreeCreator->shouldReceive('create')
        ->once()
        ->with('test-spec')
        ->andReturn('/tmp/myapp-test-spec');
    $this->app->instance(WorktreeCreator::class, $worktreeCreator);

    $runner = Mockery::mock(AgentRunner::class);
    $runner->shouldReceive('run')
        ->once()
        ->with('test-spec', Mockery::type('string'), 30, '/tmp/myapp-test-spec')
        ->andReturn(0);
    $this->app->instance(AgentRunner::class, $runner);

    $this->artisan('ralph:build', ['spec' => 'test-spec', '--create-worktree' => true])
        ->expectsOutputToContain('Creating worktree...')
        ->expectsOutputToContain('Worktree created: /tmp/myapp-test-spec')
        ->assertExitCode(0);
});

it('does not create worktree without --create-worktree flag', function () {
    $specs = Mockery::mock(SpecResolver::class);
    $specs->shouldReceive('resolve')->once()->andReturn($this->resolved);
    $this->app->instance(SpecResolver::class, $specs);

    $worktreeCreator = Mockery::mock(WorktreeCreator::class);
    $worktreeCreator->shouldNotReceive('create');
    $this->app->instance(WorktreeCreator::class, $worktreeCreator);

    $runner = Mockery::mock(AgentRunner::class);
    $runner->shouldReceive('run')
        ->once()
        ->with('test-spec', Mockery::type('string'), 30, null)
        ->andReturn(0);
    $this->app->instance(AgentRunner::class, $runner);

    $this->artisan('ralph:build', ['spec' => 'test-spec'])
        ->assertExitCode(0);
});
