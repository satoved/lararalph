<?php

use Satoved\Lararalph\Contracts\SpecRepository;
use Satoved\Lararalph\Worktree\Steps\WorktreeSetupStep;

arch('it will not use debugging functions')
    ->expect(['dd', 'dump', 'ray'])
    ->each->not->toBeUsed();

arch('worktree steps implement WorktreeSetupStep')
    ->expect('Satoved\Lararalph\Worktree\Steps')
    ->classes()
    ->toImplement(WorktreeSetupStep::class);

arch('FileSpecResolver implements SpecResolver contract')
    ->expect('Satoved\Lararalph\FileSpecRepository')
    ->toImplement(SpecRepository::class);
