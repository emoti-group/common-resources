<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Monitoring;

use Emoti\CommonResources\Services\Monitoring\ErrorReporterInterface;
use Emoti\CommonResources\Services\Monitoring\SentryErrorReporter;
use Sentry\Breadcrumb;
use Sentry\Severity;
use Sentry\SentrySdk;
use Sentry\State\HubInterface;
use Tests\TestCase;

final class SentryErrorReporterTest extends TestCase
{
    private HubInterface $hub;
    private HubInterface $previousHub;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hub = $this->createMock(HubInterface::class);
        $this->previousHub = SentrySdk::getCurrentHub();
        SentrySdk::setCurrentHub($this->hub);
    }

    protected function tearDown(): void
    {
        SentrySdk::setCurrentHub($this->previousHub);
        parent::tearDown();
    }

    public function test_add_breadcrumb_maps_type_and_level_to_sentry(): void
    {
        $this->hub->expects($this->once())
            ->method('addBreadcrumb')
            ->with($this->callback(static function (Breadcrumb $breadcrumb): bool {
                return $breadcrumb->getType() === Breadcrumb::TYPE_HTTP
                    && $breadcrumb->getLevel() === Breadcrumb::LEVEL_ERROR
                    && $breadcrumb->getCategory() === 'payments'
                    && $breadcrumb->getMessage() === 'request';
            }));

        (new SentryErrorReporter())->addBreadcrumb(
            'request',
            'payments',
            ['k' => 'v'],
            ErrorReporterInterface::LEVEL_ERROR,
            ErrorReporterInterface::TYPE_HTTP,
        );
    }

    public function test_unknown_breadcrumb_type_falls_back_to_default(): void
    {
        $this->hub->expects($this->once())
            ->method('addBreadcrumb')
            ->with($this->callback(
                static fn (Breadcrumb $breadcrumb): bool => $breadcrumb->getType() === Breadcrumb::TYPE_DEFAULT,
            ));

        (new SentryErrorReporter())->addBreadcrumb('m', 'c', [], ErrorReporterInterface::LEVEL_INFO, 'not-a-real-type');
    }

    public function test_capture_message_maps_level_to_severity(): void
    {
        $this->hub->expects($this->once())
            ->method('captureMessage')
            ->with(
                'boom',
                $this->callback(static fn (Severity $severity): bool => (string) $severity === 'error'),
                $this->isNull(),
            );

        (new SentryErrorReporter())->captureMessage('boom', ErrorReporterInterface::LEVEL_ERROR);
    }
}
