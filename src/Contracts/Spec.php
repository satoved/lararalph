<?php

namespace Satoved\Lararalph\Contracts;

final readonly class Spec
{
    public const string PRD_FILENAME = 'PRD.md';

    public const string PLAN_FILENAME = 'IMPLEMENTATION_PLAN.md';

    public function __construct(
        public string $name,
        public string $absoluteFolderPath,
        public string $absolutePrdFilePath,
        public string $absolutePlanFilePath,
    ) {}
}
