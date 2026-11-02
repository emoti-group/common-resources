# Aurora MySQL IAM authentication

The app logs in to Aurora with a short-lived IAM token signed by the EC2 instance
role, instead of a static password. Classes live in `Emoti\CommonResources\Database`.
The package does not bind them: each app opts in (see [App setup](#app-setup)).

## How it works

- `RdsIamMySqlConnector` replaces the password with a fresh token on **every connect**
  when the connection has `iam_auth` on. It covers reconnects and the read/write split,
  and with `iam_auth` off it behaves like Laravel's stock connector.
- The token is a SigV4-presigned `rds-db:connect` request, signed locally (no API call).
  It is valid for 15 minutes and checked **only at login**, so an open connection
  outlives it.
- **The token itself is never cached**: signing it is a local, sub-millisecond HMAC.
  What is cached is the **role credentials** used to sign it (see below).
- **TLS is required.** Without an SSL CA the connector throws
  `RDS IAM authentication requires TLS`.
- **Credentials:** EC2 instance role from IMDS (`iam_credentials = instance`, the
  default). Static `AWS_*` keys in `.env` are ignored.
- **Credential cache:** the role credentials are stored in the `file` cache store under
  `aws_rds_iam_instance_credentials`, with a TTL equal to their own expiry (temporary
  credentials from IMDS, typically ~6 h, rotated by AWS). They are stored as a plain
  array, so it works with `cache.serializable_classes => false`. `create()` also keeps
  one provider per process, so the credentials are memoized in memory and refreshed from
  IMDS shortly before they expire. Pass your own PSR-16 cache as the third argument of
  `RdsIamTokenProvider::fromEnvironment()` if needed. The cache must stay per host, never
  a shared store such as Redis: a shared store would hand one host's role credentials to
  hosts that run under another role.
- **Lambda:** set `iam_credentials = default` (the SDK chain, which reads the function
  role from env).

## AWS setup

Example: account `123456789012`, region `eu-central-1`, cluster `gifts-prod`,
DB user `gifts_api_app`, schema `gifts_api`.

**1. Enable IAM auth on the cluster.** Existing password logins keep working.

```bash
aws rds modify-db-cluster --db-cluster-identifier gifts-prod \
  --enable-iam-database-authentication --apply-immediately
```

**2. Get the cluster resource ID** (the policy needs it, not the name):

```bash
aws rds describe-db-clusters --db-cluster-identifier gifts-prod \
  --query 'DBClusters[0].DbClusterResourceId' --output text
# cluster-ABCDEFGHIJKLMNOPQRSTUVWXYZ
```

**3. Policy on the EC2 instance role:**

```json
{
  "Version": "2012-10-17",
  "Statement": [{
    "Effect": "Allow",
    "Action": "rds-db:connect",
    "Resource": "arn:aws:rds-db:eu-central-1:123456789012:dbuser:cluster-ABCDEFGHIJKLMNOPQRSTUVWXYZ/gifts_api_app"
  }]
}
```

**4. IMDS hop limit 2** on hosts that run app containers (Docker adds a hop):

```bash
aws ec2 modify-instance-metadata-options --instance-id i-0123456789abcdef0 \
  --http-tokens required --http-put-response-hop-limit 2
```

**5. Create the DB user** as master, over TLS:

```sql
CREATE USER 'gifts_api_app'@'%' IDENTIFIED WITH AWSAuthenticationPlugin AS 'RDS' REQUIRE SSL;

GRANT SELECT, INSERT, UPDATE, DELETE ON gifts_api.* TO 'gifts_api_app'@'%';
-- if the same user runs `php artisan migrate`:
GRANT CREATE, ALTER, DROP, INDEX, REFERENCES, CREATE TEMPORARY TABLES, LOCK TABLES
  ON gifts_api.* TO 'gifts_api_app'@'%';
```

Checks:

```sql
SELECT user, host, plugin, ssl_type FROM mysql.user WHERE plugin = 'AWSAuthenticationPlugin';
SHOW GRANTS FOR 'gifts_api_app'@'%';
```

**6. Test the login** from an app host:

```bash
HOST=gifts-prod.cluster-xxxx.eu-central-1.rds.amazonaws.com
TOKEN=$(aws rds generate-db-auth-token --hostname "$HOST" --port 3306 \
  --region eu-central-1 --username gifts_api_app)
mysql -h "$HOST" -u gifts_api_app --ssl-ca=rds-global-bundle.pem \
  --enable-cleartext-plugin --password="$TOKEN" -e 'SELECT CURRENT_USER()'
```

CA bundle: <https://truststore.pki.rds.amazonaws.com/global/global-bundle.pem>
(ship it in the app, e.g. `resources/certs/rds-global-bundle.pem`).

## App setup

`AppServiceProvider::register()`:

```php
use Emoti\CommonResources\Database\RdsIamMySqlConnector;

$this->app->bind('db.connector.mysql', static fn (): RdsIamMySqlConnector => RdsIamMySqlConnector::create());
```

`config/database.php`, on the `mysql` connection:

```php
use Emoti\CommonResources\Database\RdsIamTokenProvider;

'iam_auth' => (bool) env('DB_IAM_AUTH', false),
'iam_region' => env('DB_IAM_REGION', 'eu-central-1'),
'iam_credentials' => env('DB_IAM_CREDENTIALS', 'instance'),
'options' => extension_loaded('pdo_mysql') ? array_filter([
    RdsIamTokenProvider::sslCaOption() => env('MYSQL_ATTR_SSL_CA')
        ?: ((bool) env('DB_IAM_AUTH', false) ? base_path('resources/certs/rds-global-bundle.pem') : null),
]) : [],
```

`sslCaOption()` returns the right PDO key on PHP 8.3–8.5 (`Pdo\Mysql` exists only from 8.4).

`.env`:

```dotenv
DB_IAM_AUTH=true
DB_USERNAME=gifts_api_app
# DB_PASSWORD is ignored
```

## Rollout

1. AWS setup steps 1–6, with the IAM user created next to the old password user.
2. Deploy the app with `DB_IAM_AUTH=false`. Nothing changes.
3. In SSM set `DB_IAM_AUTH=true` and `DB_USERNAME=gifts_api_app`, then redeploy.
4. Rollback: `DB_IAM_AUTH=false` with the old username and password.
5. Later: `DROP USER` the old user and remove its password from SSM.

## Troubleshooting

| Symptom | Cause |
|---|---|
| `requires TLS` | No SSL CA on the connection. |
| `requires iam_region` | `iam_region` is missing from the connection config. |
| `Access denied ... (using password: YES)` | The policy uses the cluster name instead of the resource ID, the user was not created with `AWSAuthenticationPlugin`, IAM auth is off on the cluster, or the region/account is wrong. |
| `Error retrieving credentials from the instance profile metadata service` | The container cannot reach IMDS: the hop limit is 1. |
| Works on one host, not another | The hosts run under different instance roles. |
