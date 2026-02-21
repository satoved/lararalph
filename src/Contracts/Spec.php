<?php

namespace Satoved\Lararalph\Contracts;

final readonly class Spec
{
    public function __construct(
        public string $name,
        public string $absoluteFolderPath,
        public string $absolutePrdFilePath,
    ) {}
}
