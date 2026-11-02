<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Database;

use Aws\Credentials\CredentialProvider;
use Aws\Rds\AuthTokenGenerator;
use Illuminate\Support\Facades\Cache;
use PDO;
use Pdo\Mysql;
use Psr\SimpleCache\CacheInterface as SimpleCache;
use RuntimeException;

/**
 * Builds RDS IAM auth tokens (valid 15 minutes, checked only at connect), so a
 * token is generated per connect and never stored in the cached config.
 */
final readonly class RdsIamTokenProvider
{
    public function __construct(
        private AuthTokenGenerator $generator,
        private string $region,
    ) {}

    /**
     * @param  SimpleCache|null  $cache  where instance role credentials are cached; defaults to
     *                                   Laravel's `file` store. Must be per host, never shared
     *                                   (Redis, ...): it would hand one host's role to another.
     */
    public static function fromEnvironment(
        string $region,
        RdsIamCredentialSource $source = RdsIamCredentialSource::Instance,
        ?SimpleCache $cache = null,
    ): self {
        $credentials = match ($source) {
            RdsIamCredentialSource::Instance => CredentialProvider::memoize(CredentialProvider::cache(
                CredentialProvider::instanceProfile(),
                new RdsIamCredentialsCache($cache ?? Cache::store('file')),
                'aws_rds_iam_instance_credentials',
            )),
            RdsIamCredentialSource::Default => CredentialProvider::defaultProvider(),
        };

        return new self(new AuthTokenGenerator($credentials), $region);
    }

    public function token(string $host, int|string $port, string $username): string
    {
        return $this->generator->createToken("{$host}:{$port}", $this->region, $username);
    }

    /**
     * The PDO option key for the SSL CA. Pdo\Mysql exists from PHP 8.4 and the PDO::MYSQL_*
     * constants are deprecated from 8.5; the package still supports 8.3. A class constant
     * is resolved only when evaluated, so 8.3 never touches Pdo\Mysql.
     */
    public static function sslCaOption(): int
    {
        return \PHP_VERSION_ID >= 80400 ? Mysql::ATTR_SSL_CA : PDO::MYSQL_ATTR_SSL_CA;
    }

    /**
     * RDS accepts IAM logins over TLS only; failing here beats the server's bare "Access denied".
     *
     * @param  array<int, mixed>  $pdoOptions
     */
    public static function assertTls(array $pdoOptions): void
    {
        if (empty($pdoOptions[self::sslCaOption()])) {
            throw new RuntimeException('RDS IAM authentication requires TLS: configure the connection SSL CA.');
        }
    }
}
