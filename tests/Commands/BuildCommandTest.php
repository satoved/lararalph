<?php

use Satoved\Lararalph\AgentRunner;
use Satoved\Lararalph\SpecResolver;

beforeEach(function () {
    $this->specDir = sys_get_temp_dir().'/lararalph-test-'.uniqid();
    mkdir($this->specDir, 0755, true);
    file_put_contents($this->specDir.'/PRD.md', '# Test PRD');
    file_put_contents($this->specDir.'/IMPLEMENTATION_PLAN.md', '# Test Plan');

    $this->resolved = [
        'specPath' => $this->specDir,
        'prdFile' => $this->specDir.'/PRD.md',
        'spec' => 'test-spec',
    ];
});

afterEach(function () {
    if (is_dir($this->specDir)) {
        array_map('unlink', glob($this->specDir.'/*'));
        rmdir($this->specDir);
    }
});

it('runs build successfully with all files present', function () {
    $specs = Mockery::mock(SpecResolver::class);
    $specs->shouldReceive('resolveFromCommand')->once()->andReturn($this->resolved);
    $this->app->instance(SpecResolver::class, $specs);

    $runner = Mockery::mock(AgentRunner::class);
    $runner->shouldReceive('run')
        ->once()
        ->with('test-spec', Mockery::type('string'), 30)
        ->andReturn(0);
    $this->app->instance(AgentRunner::class, $runner);

    $this->artisan('ralph:build', ['spec' => 'test-spec'])
        ->expectsOutputToContain('Building: test-spec')
        ->assertExitCode(0);
});

it('passes custom iterations to runner', function () {
    $specs = Mockery::mock(SpecResolver::class);
    $specs->shouldReceive('resolveFromCommand')->once()->andReturn($this->resolved);
    $this->app->instance(SpecResolver::class, $specs);

    $runner = Mockery::mock(AgentRunner::class);
    $runner->shouldReceive('run')
        ->once()
        ->with('test-spec', Mockery::type('string'), 10)
        ->andReturn(0);
    $this->app->instance(AgentRunner::class, $runner);

    $this->artisan('ralph:build', ['spec' => 'test-spec', '--iterations' => 10])
        ->assertExitCode(0);
});

it('fails when spec resolution fails', function () {
    $specs = Mockery::mock(SpecResolver::class);
    $specs->shouldReceive('resolveFromCommand')->once()->andReturn(null);
    $this->app->instance(SpecResolver::class, $specs);

    $this->artisan('ralph:build', ['spec' => 'nonexistent'])
        ->assertExitCode(1);
});

it('fails when implementation plan is missing', function () {
    unlink($this->specDir.'/IMPLEMENTATION_PLAN.md');

    $specs = Mockery::mock(SpecResolver::class);
    $specs->shouldReceive('resolveFromCommand')->once()->andReturn($this->resolved);
    $this->app->instance(SpecResolver::class, $specs);

    $this->artisan('ralph:build', ['spec' => 'test-spec'])
        ->expectsOutputToContain('IMPLEMENTATION_PLAN.md not found')
        ->expectsOutputToContain("ralph:plan test-spec")
        ->assertExitCode(1);
});

it('renders the build prompt with correct file paths', function () {
    $specs = Mockery::mock(SpecResolver::class);
    $specs->shouldReceive('resolveFromCommand')->once()->andReturn($this->resolved);
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
    $specs->shouldReceive('resolveFromCommand')->once()->andReturn($this->resolved);
    $this->app->instance(SpecResolver::class, $specs);

    $runner = Mockery::mock(AgentRunner::class);
    $runner->shouldReceive('run')->once()->andReturn(2);
    $this->app->instance(AgentRunner::class, $runner);

    $this->artisan('ralph:build', ['spec' => 'test-spec'])
        ->assertExitCode(2);
});
