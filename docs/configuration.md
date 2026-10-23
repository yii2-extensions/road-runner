# Configuration reference

## Overview

This guide covers all configuration options for the RoadRunner extension, from basic setup to advanced production
scenarios.

## Basic configuration

### HTTP worker and message factories

Register the RoadRunner worker and all four PSR factories in the configuration passed to the bridge application. For
HttpSoft:

```php
use HttpSoft\Message\{ResponseFactory, ServerRequestFactory, StreamFactory, UploadedFileFactory};
use Psr\Http\Message\{
    ResponseFactoryInterface,
    ServerRequestFactoryInterface,
    StreamFactoryInterface,
    UploadedFileFactoryInterface,
};
use Spiral\RoadRunner\Http\{PSR7Worker, PSR7WorkerInterface};
use Spiral\RoadRunner\Worker;
use yii\di\Instance;

$config['container']['definitions'] = [
    PSR7WorkerInterface::class => [
        'class' => PSR7Worker::class,
        '__construct()' => [
            Worker::create(),
            Instance::of(ServerRequestFactoryInterface::class),
            Instance::of(StreamFactoryInterface::class),
            Instance::of(UploadedFileFactoryInterface::class),
        ],
    ],
    ResponseFactoryInterface::class => ResponseFactory::class,
    ServerRequestFactoryInterface::class => ServerRequestFactory::class,
    StreamFactoryInterface::class => StreamFactory::class,
    UploadedFileFactoryInterface::class => UploadedFileFactory::class,
];
```

Merge these definitions with any existing application services. The application configures the container during
construction; the runner resolves `PSR7WorkerInterface` once when `run()` starts.

### Constructor parameters

`RoadRunner` class accepts an `Application` instance.

```php
<?php

declare(strict_types=1);

use yii2\extensions\psrbridge\http\Application;
use yii2\extensions\roadrunner\RoadRunner;


$app = new Application($config);
$runner = new RoadRunner($app);
```

#### Memory management

Configure automatic worker restart based on memory usage.

```php
$app = new Application($config);

// set the recycling threshold in bytes (otherwise read from PHP memory_limit)
$app->setMemoryLimit(256 * 1024 * 1024); // 256MB

$runner = new RoadRunner($app);
```

#### Response lifecycle and recycling

For each request the runner calls `Application::handle()`, passes the returned PSR response to `respond()`, and calls
`finalize()` exactly once in `finally`. Successful emission triggers Yii after-send processing; failed processing or
emission finalizes with `false` and reports the error to RoadRunner. A failure from finalization is retained while the
worker shuts down, then propagates without reporting a second request outcome. If shutdown also fails, its exception
remains available through `getPrevious()`.

After success or a reported request failure, `clean()` checks whether allocated memory reached 90% of the configured
threshold. When recycling is needed, the runner declines the next pending dispatch with `stop()` before application
handling, so RoadRunner can retry it in a fresh worker. The loop then waits for RoadRunner's stop command. `clean()` is
a memory check, not a general test of request isolation.

Keep response streams open until `respond()` finishes. For large seekable downloads, configure `PSR7Worker::$chunkSize`
instead of casting the entire response body to a string. Keep the default buffered output for non-seekable bodies such
as pipes, because the HTTP worker rewinds bodies before streaming.

If the streaming emitter consumes a body-read failure, the runner still finalizes with `false`, skips after-send, and
recycles the worker before propagating the original exception. A partial response cannot be replaced with a second
outcome. If closing the failed stream also throws, the runner finalizes with `false` and propagates the read and
transport failures immediately; it does not submit another outcome or reuse the failed transport.

The loop releases its local response and body references before waiting for the next dispatch. A pending exception can
still retain a body through captured arguments when `zend.exception_ignore_args` is disabled. Those references remain
until the exception is released; the runner preserves the original exception and its trace instead of changing PHP
configuration or discarding diagnostic information.

### Cookies, lazy sessions, and persistent components

Preserve request cookie validation and provide its secret key through the application's secret configuration. Disable
PHP's automatic session cookies before opening a session so the bridge can emit them through the PSR response. For
example, after configuring the request key:

```php
$config['components']['session']['useCookies'] = false;
$config['components']['session']['useStrictMode'] = true;
$config['useSession'] = false; // optional: open sessions lazily in application code
$config['persistentComponents'] = ['db', 'cache'];
```

`useSession=false` skips eager opening around bootstrap. The bridge still closes a previous active session and
synchronizes the current validated cookie ID, or clears it when missing, malformed, or tampered with. It does not turn
off session support, CSRF protection, or cookie validation. Keep `request`, `response`, `errorHandler`, `session`, and
`user` out of `persistentComponents`.

Use the bridge's `http\UploadedFile` for model uploads. Upload state resets before each request, including when
`resetUploadedFiles=false`. Validate uploaded content and choose storage names in application code.

## Stateless application settings

### Configuration structure

Organize your project for RoadRunner:

```text
app-basic/
├── config/
│   ├── console.php             # Console application configuration
│   ├── db.php                  # Database configuration
│   ├── params.php              # Application parameters
│   ├── test_db.php             # Test database configuration
│   └── web.php                 # Web application configuration
├── .rr.yaml                    # RoadRunner configuration
└── rr                          # RoadRunner binary
```

### Configuration files

console.php

```php
<?php

declare(strict_types=1);

use yii\caching\FileCache;
use yii\log\FileTarget;

/** @var array<string,mixed> $params */
$params = require __DIR__ . '/params.php';
/** @var array<string,mixed> $db */
$db = require __DIR__ . '/db.php';

$config = [
    'id' => 'basic-console',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'controllerNamespace' => 'app\commands',
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
        '@tests' => '@app/tests',
    ],
    'components' => [
        'cache' => [
            'class' => FileCache::class,
        ],
        'log' => [
            'targets' => [
                [
                    'class' => FileTarget::class,
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'db' => $db,
    ],
    'params' => $params,
    /*
    'controllerMap' => [
        'fixture' => [ // Fixture generation command line.
            'class' => 'yii\faker\FixtureController',
        ],
    ],
    */
];

if (YII_ENV_DEV) {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
    ];
    // configuration adjustments for 'dev' environment
    // requires version `2.1.21` of yii2-debug module
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => 'yii\debug\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];
}

return $config;
```

db.php

```php
<?php

declare(strict_types=1);

return [
    'class' => 'yii\db\Connection',
    'dsn' => 'mysql:host=localhost;dbname=yii2basic',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8',

    // Schema cache options (for production environment)
    //'enableSchemaCache' => true,
    //'schemaCacheDuration' => 60,
    //'schemaCache' => 'cache',
];
```

params.php

```php
<?php

declare(strict_types=1);

return [
    'adminEmail' => 'admin@example.com',
    'senderEmail' => 'noreply@example.com',
    'senderName' => 'Example.com mailer',
];
```

test_db.php

```php
<?php

declare(strict_types=1);

/** @var array<string,mixed> $db */
$db = require __DIR__ . '/db.php';

// test database! Important not to run tests on production or development databases
$db['dsn'] = 'mysql:host=localhost;dbname=yii2basic_test';

return $db;
```

test.php

```php
<?php

declare(strict_types=1);

use app\models\User;
use yii\symfonymailer\{Mailer, Message};

/** @var array<string,mixed> $params */
$params = require __DIR__ . '/params.php';
/** @var array<string,mixed> $db */
$db = require __DIR__ . '/test_db.php';

/**
 * Application configuration shared by all test types
 */
return [
    'id' => 'basic-tests',
    'basePath' => dirname(__DIR__),
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    'language' => 'en-US',
    'components' => [
        'db' => $db,
        'mailer' => [
            'class' => Mailer::class,
            'viewPath' => '@app/mail',
            // send all mails to a file by default.
            'useFileTransport' => true,
            'messageClass' => Message::class,
        ],
        'assetManager' => [
            'basePath' => __DIR__ . '/../web/assets',
        ],
        'urlManager' => [
            'showScriptName' => true,
        ],
        'user' => [
            'identityClass' => User::class,
        ],
        'request' => [
            // note: this key is for testing only. Replace with a secure, random string in production!
            'cookieValidationKey' => 'your-cookie-validation-key',
            'enableCsrfValidation' => false,
            // but if you absolutely need it set cookie domain to localhost
            /*
            'csrfCookie' => [
                'domain' => 'localhost',
            ],
            */
        ],
    ],
    'params' => $params,
];
```

web.php

```php
<?php

declare(strict_types=1);

use app\models\User;
use HttpSoft\Message\{
    ResponseFactory,
    ServerRequestFactory,
    StreamFactory,
    UploadedFileFactory,
};
use Psr\Http\Message\{
    ResponseFactoryInterface,
    ServerRequestFactoryInterface,
    StreamFactoryInterface,
    UploadedFileFactoryInterface,
};
use Spiral\RoadRunner\Http\{PSR7Worker, PSR7WorkerInterface};
use Spiral\RoadRunner\Worker;
use yii\caching\FileCache;
use yii\di\Instance;
use yii\gii\Module;
use yii\symfonymailer\Mailer;
use yii2\extensions\debug\WorkerDebugModule;


/** @var array<string,mixed> $params */
$params = require __DIR__ . '/params.php';
/** @var array<string,mixed> $db */
$db = require __DIR__ . '/db.php';

$config = [
    'id' => 'basic',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    'components' => [
        'request' => [
            // note: this key is for testing only. Replace with a secure, random string in production!
            'cookieValidationKey' => 'your-cookie-validation-key',
        ],
        'cache' => [
            'class' => FileCache::class,
        ],
        'session' => [
            'useCookies' => false,
            'useStrictMode' => true,
        ],
        'user' => [
            'identityClass' => User::class,
            'enableAutoLogin' => false,
        ],
        'errorHandler' => [
            'errorAction' => 'site/error',
        ],
        'mailer' => [
            'class' => Mailer::class,
            'viewPath' => '@app/mail',
            // send all mails to a file by default.
            'useFileTransport' => true,
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'db' => $db,
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
        ],
    ],
    'container' => [
        'definitions' => [
            PSR7WorkerInterface::class => [
                'class' => PSR7Worker::class,
                '__construct()' => [
                    Worker::create(),
                    Instance::of(ServerRequestFactoryInterface::class),
                    Instance::of(StreamFactoryInterface::class),
                    Instance::of(UploadedFileFactoryInterface::class),
                ],
            ],
            ResponseFactoryInterface::class => ResponseFactory::class,
            ServerRequestFactoryInterface::class => ServerRequestFactory::class,
            StreamFactoryInterface::class => StreamFactory::class,
            UploadedFileFactoryInterface::class => UploadedFileFactory::class,
        ],
    ],
    'params' => $params,
];

if (YII_ENV_DEV) {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => WorkerDebugModule::class,
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];

    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => Module::class,
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];
}

return $config;
```

### DELETE form bodies

RoadRunner 2025.1.15 with HTTP plugin v5.3.0 drops URL-encoded DELETE fields before sending the request to PHP. The PHP
adapter cannot recover those fields. JSON DELETE bodies work with a configured JSON parser; applications that explicitly
support method override can accept POST with `_method=DELETE` or `X-Http-Method-Override: DELETE`. Method override
changes the wire method and must be part of the endpoint and proxy policy.

Keep native body processing enabled when using multipart uploads. In this runtime version, enabling `raw_body` also
bypasses multipart form and upload processing. Native DELETE URL-encoded clients require a runtime whose form handling
has been verified to preserve their fields.

[Back to documentation](../README.md#documentation)
