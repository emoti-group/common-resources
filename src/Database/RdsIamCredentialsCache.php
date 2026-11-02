<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Database;

use Aws\CacheInterface;
use Aws\Credentials\Credentials;
use Aws\Credentials\CredentialsInterface;
use Psr\SimpleCache\CacheInterface as SimpleCache;

/**
 * Stores AWS credentials as a plain array, not a serialized object: a Laravel cache with
 * `serializable_classes => false` (gifts-cms) would read a Credentials object back as
 * __PHP_Incomplete_Class, which the SDK silently treats as a miss.
 */
final readonly class RdsIamCredentialsCache implements CacheInterface
{
    public function __construct(private SimpleCache $cache) {}

    public function get($key): ?CredentialsInterface
    {
        $value = $this->cache->get($key);

        if (! is_array($value) || ! isset($value['key'], $value['secret'])) {
            return null;
        }

        return new Credentials(
            $value['key'],
            $value['secret'],
            $value['token'] ?? null,
            $value['expires'] ?? null,
            $value['accountId'] ?? null,
        );
    }

    public function set($key, $value, $ttl = 0): void
    {
        if (! $value instanceof CredentialsInterface) {
            return;
        }

        $this->cache->set($key, $value->toArray(), $ttl > 0 ? $ttl : null);
    }

    public function remove($key): void
    {
        $this->cache->delete($key);
    }
}
