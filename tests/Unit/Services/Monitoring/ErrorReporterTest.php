<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Monitoring;

use Emoti\CommonResources\Facades\ErrorReporter;
use Emoti\CommonResources\Services\Monitoring\ErrorReporterInterface;
use Emoti\CommonResources\Services\Monitoring\SentryErrorReporter;
use Tests\TestCase;

final class ErrorReporterTest extends TestCase
{
    public function test_interface_is_bound_in_container(): void
    {
        $this->assertTrue($this->app->bound(ErrorReporterInterface::class));
    }

    public function test_default_implementation_is_sentry(): void
    {
        $this->assertInstanceOf(
            SentryErrorReporter::class,
            $this->app->make(ErrorReporterInterface::class),
        );
    }

    public function test_facade_resolves_to_bound_implementation(): void
    {
        $this->assertInstanceOf(ErrorReporterInterface::class, ErrorReporter::getFacadeRoot());
    }
}
