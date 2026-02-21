<?php

use Satoved\Lararalph\Contracts\SpecRepository;
use Satoved\Lararalph\Contracts\WorktreeSetupStep;

arch('it will not use debugging functions')
    ->expect(['dd', 'dump', 'ray'])
    ->each->not->toBeUsed();

arch('worktree steps implement WorktreeSetupStep')
    ->expect('Satoved\Lararalph\Worktree\Steps')
    ->classes()
    ->toImplement(WorktreeSetupStep::class);

arch('FileSpecResolver implements SpecResolver contract')
    ->expect('Satoved\Lararalph\Repositories\FileSpecRepository')
    ->toImplement(SpecRepository::class);
