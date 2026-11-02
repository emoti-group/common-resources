<?php

declare(strict_types=1);

namespace Tests\Integration\Database;

use Aws\Credentials\Credentials;
use Aws\Credentials\CredentialsInterface;
use Emoti\CommonResources\Database\RdsIamCredentialsCache;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

final class RdsIamCredentialsCacheTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        // As in gifts-cms: the file store refuses to unserialize objects.
        $app['config']->set('cache.serializable_classes', false);
        $app['config']->set('cache.stores.file.path', sys_get_temp_dir() . '/rds-iam-cache-test-' . getmypid());
    }

    protected function tearDown(): void
    {
        Cache::store('file')->clear();

        parent::tearDown();
    }

    public function test_round_trips_credentials_through_a_store_without_serializable_classes(): void
    {
        $cache = new RdsIamCredentialsCache(Cache::store('file'));
        $expires = time() + 3600;

        $cache->set('creds', new Credentials('AKID', 'secret', 'session', $expires, '123456789012'), 3600);
        $found = $cache->get('creds');

        $this->assertInstanceOf(CredentialsInterface::class, $found);
        $this->assertSame('AKID', $found->getAccessKeyId());
        $this->assertSame('secret', $found->getSecretKey());
        $this->assertSame('session', $found->getSecurityToken());
        $this->assertSame($expires, $found->getExpiration());
        $this->assertSame('123456789012', $found->toArray()['accountId']);
        $this->assertFalse($found->isExpired());
    }

    public function test_miss_and_remove(): void
    {
        $cache = new RdsIamCredentialsCache(Cache::store('file'));

        $this->assertNull($cache->get('missing'));

        $cache->set('creds', new Credentials('AKID', 'secret'), 60);
        $cache->remove('creds');

        $this->assertNull($cache->get('creds'));
    }
}
