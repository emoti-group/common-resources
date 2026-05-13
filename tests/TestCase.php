<?php

declare(strict_types=1);

namespace Tests;

use Emoti\CommonResources\CommonResourcesServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Spatie\LaravelData\LaravelDataServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            LaravelDataServiceProvider::class,
            CommonResourcesServiceProvider::class,
        ];
    }
}
