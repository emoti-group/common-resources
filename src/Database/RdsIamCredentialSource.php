<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Database;

/**
 * Where the AWS credentials that sign an RDS IAM token come from.
 * See docs/rds-iam-auth.md.
 */
enum RdsIamCredentialSource: string
{
    /**
     * EC2 instance role via IMDS, ignoring AWS_* keys in the environment. For apps
     * on EC2 whose .env carries static keys (S3, DynamoDB, ...) that must not log in to the DB.
     */
    case Instance = 'instance';

    /**
     * The SDK default chain (env, profile, ECS/EC2 role). For Lambda, whose runtime
     * puts the function role's credentials in the environment.
     */
    case Default = 'default';
}
