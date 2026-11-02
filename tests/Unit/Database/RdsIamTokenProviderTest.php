<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use Aws\Credentials\Credentials;
use Aws\Rds\AuthTokenGenerator;
use Emoti\CommonResources\Database\RdsIamCredentialSource;
use Emoti\CommonResources\Database\RdsIamTokenProvider;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use PDO;
use RuntimeException;
use Tests\TestCase;

final class RdsIamTokenProviderTest extends TestCase
{
    public function test_token_is_presigned_connect_request_for_endpoint_and_user(): void
    {
        $token = self::provider('eu-central-1')->token('db.cluster.eu-central-1.rds.amazonaws.com', '3306', 'app_user');

        $this->assertStringStartsWith('db.cluster.eu-central-1.rds.amazonaws.com:3306/?', $token);
        $this->assertStringContainsString('Action=connect', $token);
        $this->assertStringContainsString('DBUser=app_user', $token);
        $this->assertStringContainsString('AKIDEXAMPLE%2F', $token);
        $this->assertStringContainsString('%2Feu-central-1%2Frds-db%2Faws4_request', $token);
        $this->assertStringContainsString('X-Amz-Expires=900', $token);
    }

    public function test_ssl_ca_option_matches_the_pdo_mysql_constant(): void
    {
        $expected = \PHP_VERSION_ID >= 80400 ? \Pdo\Mysql::ATTR_SSL_CA : PDO::MYSQL_ATTR_SSL_CA;

        $this->assertSame($expected, RdsIamTokenProvider::sslCaOption());
    }

    public function test_assert_tls_passes_when_ca_is_configured(): void
    {
        RdsIamTokenProvider::assertTls([RdsIamTokenProvider::sslCaOption() => '/certs/rds-global-bundle.pem']);

        $this->addToAssertionCount(1);
    }

    public function test_assert_tls_fails_without_ca(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('requires TLS');

        RdsIamTokenProvider::assertTls([]);
    }

    public function test_builds_from_environment_for_every_credential_source(): void
    {
        foreach (RdsIamCredentialSource::cases() as $source) {
            $this->assertInstanceOf(RdsIamTokenProvider::class, RdsIamTokenProvider::fromEnvironment('eu-central-1', $source));
        }
    }

    public function test_builds_from_environment_with_a_caller_provided_cache(): void
    {
        $cache = new Repository(new ArrayStore());

        $this->assertInstanceOf(RdsIamTokenProvider::class, RdsIamTokenProvider::fromEnvironment('eu-central-1', cache: $cache));
    }

    public static function provider(string $region): RdsIamTokenProvider
    {
        return new RdsIamTokenProvider(
            new AuthTokenGenerator(new Credentials('AKIDEXAMPLE', 'secret')),
            $region,
        );
    }
}
