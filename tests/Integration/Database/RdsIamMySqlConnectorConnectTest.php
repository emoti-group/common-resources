<?php

declare(strict_types=1);

namespace Tests\Integration\Database;

use Aws\Credentials\Credentials;
use Aws\Rds\AuthTokenGenerator;
use Closure;
use Emoti\CommonResources\Database\RdsIamCredentialSource;
use Emoti\CommonResources\Database\RdsIamMySqlConnector;
use Emoti\CommonResources\Database\RdsIamTokenProvider;
use Illuminate\Support\Facades\DB;
use PDO;
use PDOException;
use Tests\TestCase;

/**
 * Goes through Laravel's ConnectionFactory, so the read/write split and host fallback
 * really call connect(). Nothing listens on port 1: every attempt fails after the token
 * is signed, which is all we check.
 */
final class RdsIamMySqlConnectorConnectTest extends TestCase
{
    /** @var list<string> */
    private array $signedEndpoints = [];

    protected function setUp(): void
    {
        parent::setUp();

        $record = function (string $endpoint): void {
            $this->signedEndpoints[] = $endpoint;
        };

        $generator = new class(new Credentials('AKIDEXAMPLE', 'secret'), $record) extends AuthTokenGenerator
        {
            /** @param  Closure(string): void  $record */
            public function __construct(Credentials $credentials, private readonly Closure $record)
            {
                parent::__construct($credentials);
            }

            public function createToken($endpoint, $region, $username, $lifetime = 15): string
            {
                ($this->record)((string) $endpoint);

                return parent::createToken($endpoint, $region, $username, $lifetime);
            }
        };

        app()->bind('db.connector.mysql', static fn (): RdsIamMySqlConnector => new RdsIamMySqlConnector(
            static fn (string $region, RdsIamCredentialSource $source): RdsIamTokenProvider => new RdsIamTokenProvider($generator, $region),
        ));

        config()->set('database.connections.iam', [
            'driver' => 'mysql',
            'read' => ['host' => ['localhost', '127.0.0.1']],
            'write' => ['host' => ['127.0.0.1']],
            'port' => 1,
            'database' => 'app',
            'username' => 'app_user',
            'password' => '',
            'iam_auth' => true,
            'iam_region' => 'eu-central-1',
            'options' => [
                RdsIamTokenProvider::sslCaOption() => '/certs/rds-global-bundle.pem',
                PDO::ATTR_TIMEOUT => 1,
            ],
        ]);
    }

    public function test_signs_a_token_for_every_read_host_tried(): void
    {
        $this->attempt(static fn () => DB::connection('iam')->getReadPdo());

        $this->assertEqualsCanonicalizing(['localhost:1', '127.0.0.1:1'], $this->signedEndpoints);
    }

    public function test_signs_a_token_for_the_write_host(): void
    {
        $this->attempt(static fn () => DB::connection('iam')->getPdo());

        $this->assertSame(['127.0.0.1:1'], $this->signedEndpoints);
    }

    private function attempt(callable $connect): void
    {
        try {
            $connect();
            $this->fail('Expected the connection to fail: nothing listens on port 1.');
        } catch (PDOException) {
        }
    }
}
