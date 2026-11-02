<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use Emoti\CommonResources\Database\RdsIamCredentialSource;
use Emoti\CommonResources\Database\RdsIamMySqlConnector;
use Emoti\CommonResources\Database\RdsIamTokenProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ValueError;

final class RdsIamMySqlConnectorTest extends TestCase
{
    private ?RdsIamCredentialSource $usedSource = null;

    public function test_keeps_config_when_iam_auth_is_off(): void
    {
        $config = $this->config(['iam_auth' => false]);

        $this->assertSame($config, $this->connector()->withIamToken($config));
    }

    public function test_keeps_password_when_iam_auth_is_missing(): void
    {
        $config = $this->config();
        unset($config['iam_auth']);

        $this->assertSame('static-password', $this->connector()->withIamToken($config)['password']);
    }

    public function test_replaces_password_with_token_for_host_user_and_region(): void
    {
        $resolved = $this->connector()->withIamToken($this->config(['iam_region' => 'eu-west-1']));

        $this->assertStringStartsWith('writer.example.com:3306/?', $resolved['password']);
        $this->assertStringContainsString('DBUser=app_user', $resolved['password']);
        $this->assertStringContainsString('%2Feu-west-1%2Frds-db%2F', $resolved['password']);
        $this->assertSame('app_user', $resolved['username']);
    }

    public function test_signs_token_for_the_host_being_connected(): void
    {
        $resolved = $this->connector()->withIamToken($this->config(['host' => 'reader.example.com']));

        $this->assertStringStartsWith('reader.example.com:3306/?', $resolved['password']);
    }

    public function test_defaults_to_instance_role_credentials(): void
    {
        $this->connector()->withIamToken($this->config());

        $this->assertSame(RdsIamCredentialSource::Instance, $this->usedSource);
    }

    public function test_passes_configured_credential_source(): void
    {
        $this->connector()->withIamToken($this->config(['iam_credentials' => 'default']));

        $this->assertSame(RdsIamCredentialSource::Default, $this->usedSource);
    }

    public function test_rejects_unknown_credential_source(): void
    {
        $this->expectException(ValueError::class);

        $this->connector()->withIamToken($this->config(['iam_credentials' => 'static']));
    }

    public function test_refuses_iam_auth_without_region(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('requires iam_region');

        $config = $this->config();
        unset($config['iam_region']);

        $this->connector()->withIamToken($config);
    }

    public function test_defaults_to_port_3306_when_config_has_none(): void
    {
        $config = $this->config();
        unset($config['port']);

        $this->assertStringStartsWith('writer.example.com:3306/?', $this->connector()->withIamToken($config)['password']);
    }

    public function test_refuses_iam_auth_without_tls(): void
    {
        $this->expectException(RuntimeException::class);

        $this->connector()->withIamToken($this->config(['options' => []]));
    }

    private function connector(): RdsIamMySqlConnector
    {
        return new RdsIamMySqlConnector(function (string $region, RdsIamCredentialSource $source): RdsIamTokenProvider {
            $this->usedSource = $source;

            return RdsIamTokenProviderTest::provider($region);
        });
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function config(array $overrides = []): array
    {
        return [
            'driver' => 'mysql',
            'host' => 'writer.example.com',
            'port' => '3306',
            'username' => 'app_user',
            'password' => 'static-password',
            'iam_auth' => true,
            'iam_region' => 'eu-central-1',
            'options' => [RdsIamTokenProvider::sslCaOption() => '/certs/rds-global-bundle.pem'],
            ...$overrides,
        ];
    }
}
