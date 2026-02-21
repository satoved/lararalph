<?php

namespace Satoved\Lararalph\Contracts;

interface SpecRepository
{
    /** List spec directory names in the backlog directory. */
    public function getBacklogSpecs(): array;

    /**
     * Resolve a spec name to a Spec.
     *
     * @throws \Satoved\Lararalph\Exceptions\SpecFolderDoesNotExist
     * @throws \Satoved\Lararalph\Exceptions\SpecFolderDoesNotContainPrdFile
     */
    public function resolve(string $spec): Spec;

    /** Move a completed spec from backlog to complete. */
    public function complete(Spec $spec): void;
}
