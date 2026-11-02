<?php

declare(strict_types=1);

namespace Tests\Integration\Database;

use Closure;
use Emoti\CommonResources\Database\RdsIamCredentialSource;
use Emoti\CommonResources\Database\RdsIamMySqlConnector;
use ReflectionProperty;
use Tests\TestCase;

final class RdsIamMySqlConnectorCreateTest extends TestCase
{
    public function test_reuses_one_provider_per_region_and_source_across_connectors(): void
    {
        $first = $this->factory(RdsIamMySqlConnector::create());
        $second = $this->factory(RdsIamMySqlConnector::create());

        $provider = $first('eu-central-1', RdsIamCredentialSource::Instance);

        $this->assertSame($provider, $first('eu-central-1', RdsIamCredentialSource::Instance));
        $this->assertSame($provider, $second('eu-central-1', RdsIamCredentialSource::Instance));
        $this->assertNotSame($provider, $first('eu-west-1', RdsIamCredentialSource::Instance));
        $this->assertNotSame($provider, $first('eu-central-1', RdsIamCredentialSource::Default));
    }

    private function factory(RdsIamMySqlConnector $connector): Closure
    {
        $factory = (new ReflectionProperty($connector, 'tokenProviderFactory'))->getValue($connector);
        $this->assertInstanceOf(Closure::class, $factory);

        return $factory;
    }
}
