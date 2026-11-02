<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Database;

use Closure;
use Illuminate\Database\Connectors\MySqlConnector;
use Override;
use PDO;
use RuntimeException;

/**
 * Swaps the password for a fresh RDS IAM token on every connect when the
 * connection has `iam_auth` on. Hooking the connector (not the config) also
 * covers reconnects, purged connections and the read/write host split.
 *
 * Not bound by this package: each app opts in with one line in its provider.
 * See docs/rds-iam-auth.md.
 */
final class RdsIamMySqlConnector extends MySqlConnector
{
    /**
     * One provider per process and region/source, so its memoized credentials
     * survive across connects instead of re-reading the cache or IMDS each time.
     *
     * @var array<string, RdsIamTokenProvider>
     */
    private static array $providers = [];

    /**
     * @param  Closure(string, RdsIamCredentialSource): RdsIamTokenProvider  $tokenProviderFactory
     */
    public function __construct(private readonly Closure $tokenProviderFactory) {}

    public static function create(): self
    {
        return new self(static fn (string $region, RdsIamCredentialSource $source): RdsIamTokenProvider
            => self::$providers["{$source->value}:{$region}"] ??= RdsIamTokenProvider::fromEnvironment($region, $source));
    }

    /**
     * @param  array<string, mixed>  $config
     */
    #[Override]
    public function connect(array $config): PDO
    {
        return parent::connect($this->withIamToken($config));
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function withIamToken(array $config): array
    {
        if (! ($config['iam_auth'] ?? false)) {
            return $config;
        }

        RdsIamTokenProvider::assertTls($config['options'] ?? []);

        if (empty($config['iam_region'])) {
            throw new RuntimeException('RDS IAM authentication requires iam_region.');
        }

        $source = RdsIamCredentialSource::from((string) ($config['iam_credentials'] ?? RdsIamCredentialSource::Instance->value));

        $config['password'] = ($this->tokenProviderFactory)((string) $config['iam_region'], $source)
            ->token((string) $config['host'], $config['port'] ?? 3306, (string) $config['username']);

        return $config;
    }
}
