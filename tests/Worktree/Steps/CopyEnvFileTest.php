<?php

use Satoved\Lararalph\Worktree\Steps\CopyEnvFile;

beforeEach(function () {
    $this->sourceDir = sys_get_temp_dir().'/lararalph-env-source-'.uniqid();
    $this->targetDir = sys_get_temp_dir().'/lararalph-env-target-'.uniqid();
    mkdir($this->sourceDir, 0755, true);
    mkdir($this->targetDir, 0755, true);
});

afterEach(function () {
    exec("rm -rf {$this->sourceDir} {$this->targetDir}");
});

it('transforms APP_URL with https', function () {
    file_put_contents($this->sourceDir.'/.env', implode("\n", [
        'APP_NAME=MyApp',
        'APP_URL=https://myapp.test',
        'DB_HOST=127.0.0.1',
    ]));

    (new CopyEnvFile)->handle($this->targetDir, $this->sourceDir, 'user-notifications');

    $contents = file_get_contents($this->targetDir.'/.env');

    expect($contents)->toContain('APP_URL=https://myapp-user-notifications.test');
    expect($contents)->toContain('APP_NAME=MyApp');
    expect($contents)->toContain('DB_HOST=127.0.0.1');
});

it('transforms APP_URL with http', function () {
    file_put_contents($this->sourceDir.'/.env', 'APP_URL=http://myapp.test');

    (new CopyEnvFile)->handle($this->targetDir, $this->sourceDir, 'my-feature');

    $contents = file_get_contents($this->targetDir.'/.env');

    expect($contents)->toContain('APP_URL=http://myapp-my-feature.test');
});

it('transforms APP_URL with port', function () {
    file_put_contents($this->sourceDir.'/.env', 'APP_URL=https://myapp.test:8080');

    (new CopyEnvFile)->handle($this->targetDir, $this->sourceDir, 'feat');

    $contents = file_get_contents($this->targetDir.'/.env');

    expect($contents)->toContain('APP_URL=https://myapp-feat.test:8080');
});

it('is a no-op when .env is missing', function () {
    (new CopyEnvFile)->handle($this->targetDir, $this->sourceDir, 'my-spec');

    expect(file_exists($this->targetDir.'/.env'))->toBeFalse();
});

it('handles subdomain URLs', function () {
    file_put_contents($this->sourceDir.'/.env', 'APP_URL=https://api.myapp.test');

    (new CopyEnvFile)->handle($this->targetDir, $this->sourceDir, 'feat');

    $contents = file_get_contents($this->targetDir.'/.env');

    expect($contents)->toContain('APP_URL=https://api.myapp-feat.test');
});
