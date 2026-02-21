<?php

namespace Satoved\Lararalph\Enums;

enum LoopRunnerResult: int
{
    case FullyComplete = 0;
    case Error = 1;
    case MaxIterationsReached = 2;
}
