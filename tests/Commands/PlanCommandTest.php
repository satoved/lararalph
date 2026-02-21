<?php

use Satoved\Lararalph\AgentRunner;
use Satoved\Lararalph\Contracts\Spec;
use Satoved\Lararalph\Contracts\SpecResolver;
use Satoved\Lararalph\Worktree\WorktreeCreator;

beforeEach(function () {
    $this->specDir = sys_get_temp_dir().'/lararalph-test-'.uniqid();
    mkdir($this->specDir, 0755, true);
    file_put_contents($this->specDir.'/PRD.md', '# Test PRD');

    $this->resolved = new Spec(
        name: 'test-spec',
        absoluteFolderPath: $this->specDir,
        absolutePrdFilePath: $this->specDir.'/PRD.md',
    );
});

afterEach(function () {
    if (is_dir($this->specDir)) {
        array_map('unlink', glob($this->specDir.'/*'));
        rmdir($this->specDir);
    }
});

it('creates a plan successfully when no plan exists', function () {
    $specs = Mockery::mock(SpecResolver::class);
    $specs->shouldReceive('resolve')
        ->once()
        ->with('test-spec')
        ->andReturn($this->resolved);
    $this->app->instance(SpecResolver::class, $specs);

    $runner = Mockery::mock(AgentRunner::class);
    $runner->shouldReceive('run')
        ->once()
        ->with('test-spec', Mockery::type('string'), 1, null)
        ->andReturn(0);
    $this->app->instance(AgentRunner::class, $runner);

    $this->artisan('ralph:plan', ['spec' => 'test-spec'])
        ->expectsOutputToContain('Creating implementation plan for: test-spec')
        ->assertExitCode(0);
});

it('fails when spec resolution returns null', function () {
    $specs = Mockery::mock(SpecResolver::class);
    $specs->shouldReceive('resolve')->once()->with('nonexistent')->andReturn(null);
    $this->app->instance(SpecResolver::class, $specs);

    $this->artisan('ralph:plan', ['spec' => 'nonexistent'])
        ->expectsOutputToContain('Spec not found or PRD.md missing: nonexistent')
        ->assertExitCode(1);
});

it('fails when no specs available and no argument given', function () {
    $specs = Mockery::mock(SpecResolver::class);
    $specs->shouldReceive('choose')->once()->with('Select a spec to plan')->andReturn(null);
    $this->app->instance(SpecResolver::class, $specs);

    $this->artisan('ralph:plan')
        ->expectsOutputToContain('No specs found in specs/backlog/')
        ->assertExitCode(1);
});

it('fails when plan already exists without force', function () {
    file_put_contents($this->specDir.'/IMPLEMENTATION_PLAN.md', '# Existing Plan');

    $specs = Mockery::mock(SpecResolver::class);
    $specs->shouldReceive('resolve')->once()->andReturn($this->resolved);
    $this->app->instance(SpecResolver::class, $specs);

    $this->artisan('ralph:plan', ['spec' => 'test-spec'])
        ->expectsOutputToContain('IMPLEMENTATION_PLAN.md already exists')
        ->expectsOutputToContain('Use --force to regenerate')
        ->assertExitCode(1);
});

it('regenerates plan with force flag', function () {
    file_put_contents($this->specDir.'/IMPLEMENTATION_PLAN.md', '# Existing Plan');

    $specs = Mockery::mock(SpecResolver::class);
    $specs->shouldReceive('resolve')->once()->andReturn($this->resolved);
    $this->app->instance(SpecResolver::class, $specs);

    $runner = Mockery::mock(AgentRunner::class);
    $runner->shouldReceive('run')->once()->with('test-spec', Mockery::type('string'), 1, null)->andReturn(0);
    $this->app->instance(AgentRunner::class, $runner);

    $this->artisan('ralph:plan', ['spec' => 'test-spec', '--force' => true])
        ->expectsOutputToContain('Creating implementation plan for: test-spec')
        ->assertExitCode(0);
});

it('passes existing plan file path to prompt when forcing regeneration', function () {
    file_put_contents($this->specDir.'/IMPLEMENTATION_PLAN.md', '# Existing Plan');

    $specs = Mockery::mock(SpecResolver::class);
    $specs->shouldReceive('resolve')->once()->andReturn($this->resolved);
    $this->app->instance(SpecResolver::class, $specs);

    $runner = Mockery::mock(AgentRunner::class);
    $runner->shouldReceive('run')
        ->once()
        ->withArgs(function ($spec, $prompt, $iterations) {
            return str_contains($prompt, 'IMPLEMENTATION_PLAN.md')
                && str_contains($prompt, 'Plan only. Do NOT implement anything');
        })
        ->andReturn(0);
    $this->app->instance(AgentRunner::class, $runner);

    $this->artisan('ralph:plan', ['spec' => 'test-spec', '--force' => true])
        ->assertExitCode(0);
});

it('shows success message when plan file is created', function () {
    $specs = Mockery::mock(SpecResolver::class);
    $specs->shouldReceive('resolve')->once()->andReturn($this->resolved);
    $this->app->instance(SpecResolver::class, $specs);

    $runner = Mockery::mock(AgentRunner::class);
    $runner->shouldReceive('run')
        ->once()
        ->andReturnUsing(function () {
            file_put_contents($this->specDir.'/IMPLEMENTATION_PLAN.md', '# New Plan');

            return 0;
        });
    $this->app->instance(AgentRunner::class, $runner);

    $this->artisan('ralph:plan', ['spec' => 'test-spec'])
        ->expectsOutputToContain('Implementation plan created')
        ->assertExitCode(0);
});

it('shows warning when runner succeeds but plan file not created', function () {
    $specs = Mockery::mock(SpecResolver::class);
    $specs->shouldReceive('resolve')->once()->andReturn($this->resolved);
    $this->app->instance(SpecResolver::class, $specs);

    $runner = Mockery::mock(AgentRunner::class);
    $runner->shouldReceive('run')->once()->andReturn(0);
    $this->app->instance(AgentRunner::class, $runner);

    $this->artisan('ralph:plan', ['spec' => 'test-spec'])
        ->expectsOutputToContain('IMPLEMENTATION_PLAN.md was not created')
        ->assertExitCode(0);
});

it('propagates runner exit code on failure', function () {
    $specs = Mockery::mock(SpecResolver::class);
    $specs->shouldReceive('resolve')->once()->andReturn($this->resolved);
    $this->app->instance(SpecResolver::class, $specs);

    $runner = Mockery::mock(AgentRunner::class);
    $runner->shouldReceive('run')->once()->andReturn(1);
    $this->app->instance(AgentRunner::class, $runner);

    $this->artisan('ralph:plan', ['spec' => 'test-spec'])
        ->assertExitCode(1);
});

it('always runs with 1 iteration', function () {
    $specs = Mockery::mock(SpecResolver::class);
    $specs->shouldReceive('resolve')->once()->andReturn($this->resolved);
    $this->app->instance(SpecResolver::class, $specs);

    $runner = Mockery::mock(AgentRunner::class);
    $runner->shouldReceive('run')
        ->once()
        ->with('test-spec', Mockery::type('string'), 1, null)
        ->andReturn(0);
    $this->app->instance(AgentRunner::class, $runner);

    $this->artisan('ralph:plan', ['spec' => 'test-spec'])
        ->assertExitCode(0);
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
        ->with('test-spec', Mockery::type('string'), 1, '/tmp/myapp-test-spec')
        ->andReturn(0);
    $this->app->instance(AgentRunner::class, $runner);

    $this->artisan('ralph:plan', ['spec' => 'test-spec', '--create-worktree' => true])
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
        ->with('test-spec', Mockery::type('string'), 1, null)
        ->andReturn(0);
    $this->app->instance(AgentRunner::class, $runner);

    $this->artisan('ralph:plan', ['spec' => 'test-spec'])
        ->assertExitCode(0);
});
