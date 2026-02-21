<?php

namespace Satoved\Lararalph\Worktree\Setup;

use Satoved\Lararalph\Contracts\WorktreeSetupStep;

class CopyEnvFile implements WorktreeSetupStep
{
    public function handle(string $worktreePath, string $sourcePath, string $spec): void
    {
        $sourceEnv = $sourcePath.'/.env';

        if (! file_exists($sourceEnv)) {
            return;
        }

        $contents = file_get_contents($sourceEnv);

        $contents = preg_replace_callback(
            '/^(APP_URL\s*=\s*)(https?:\/\/)([^:\s]+)(.*)/m',
            function ($matches) use ($spec) {
                $scheme = $matches[2];
                $host = $matches[3];
                $rest = $matches[4];

                // Transform domain: myapp.test → myapp-{spec}.test
                $dotPos = strrpos($host, '.');
                if ($dotPos !== false) {
                    $host = substr($host, 0, $dotPos).'-'.$spec.substr($host, $dotPos);
                } else {
                    $host = $host.'-'.$spec;
                }

                return $matches[1].$scheme.$host.$rest;
            },
            $contents
        );

        file_put_contents($worktreePath.'/.env', $contents);
    }
}
