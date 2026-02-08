<?php

namespace Satoved\Lararalph\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Satoved\Lararalph\LararalphServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app)
    {
        return [
            LararalphServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        //
    }
}
