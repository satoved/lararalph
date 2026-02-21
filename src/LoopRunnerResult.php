<?php

namespace Satoved\Lararalph;

enum LoopRunnerResult: int
{
    case Complete = 0;
    case Error = 1;
    case MaxIterationsReached = 2;
}
