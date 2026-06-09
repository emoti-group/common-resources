<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Monitoring;

use Emoti\CommonResources\CommonResourcesServiceProvider;
use Emoti\CommonResources\Services\Monitoring\ErrorReporterInterface;
use Tests\TestCase;

final class ErrorReporterConfigOverrideTest extends TestCase
{
    public function test_config_key_overrides_the_bound_implementation(): void
    {
        config()->set('common-resources.error_reporter', FakeErrorReporter::class);

        (new CommonResourcesServiceProvider($this->app))->register();

        $this->assertInstanceOf(
            FakeErrorReporter::class,
            $this->app->make(ErrorReporterInterface::class),
        );
    }
}
