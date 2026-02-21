<?php

namespace Satoved\Lararalph\Contracts;

final readonly class ResolvedSpec
{
    public function __construct(
        public string $spec,
        public string $specPath,
        public string $prdFile,
    ) {}
}
