<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Queue\Client;

use DaveLiddament\PhpLanguageExtensions\NamespaceVisibility;
use Emoti\CommonResources\Support\Config\Config;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Connection\AMQPConnectionConfig;
use PhpAmqpLib\Connection\AMQPConnectionFactory;

#[NamespaceVisibility(namespace: 'Emoti\CommonResources\Queue')]
final class RabbitMqConnectionFactory
{
    private static ?AbstractConnection $connection = null;

    public static function create(): AbstractConnection
    {
        if (self::$connection === null || !self::$connection->isConnected()) {
            $connectionConfig = self::buildConnectionConfig();
            self::$connection = AMQPConnectionFactory::create($connectionConfig);
        }

        return self::$connection;
    }

    private static function buildConnectionConfig(): AMQPConnectionConfig
    {
        $config = new AMQPConnectionConfig();
        $config->setHost(Config::get('rabbitmq.host'));
        $config->setPort((int)Config::get('rabbitmq.port'));
        $config->setUser(Config::get('rabbitmq.user'));
        $config->setPassword(Config::get('rabbitmq.password'));
        $config->setKeepalive(true);
        $config->setHeartbeat(60);
        $config->setReadTimeout(120);
        $config->setWriteTimeout(120);
        $config->setConnectionTimeout(30);

        if (self::rabbitIsRemote()) {
            $config->setIsSecure(true);
            $config->setSslVerify(false);
            $config->setSslVerifyName(false);
        }

        return $config;
    }

    private static function rabbitIsRemote(): bool
    {
        return
            !str_contains(Config::get('rabbitmq.host'), 'common-resources')
            || !str_contains(Config::get('rabbitmq.host'), 'rabbitmq')
            || !str_contains(Config::get('rabbitmq.user'), 'dev')
            || !str_contains(Config::get('rabbitmq.password'), 'dev');
    }
}
