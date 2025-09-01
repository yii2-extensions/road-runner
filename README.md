<p align="center">
    <a href="https://github.com/yii2-extensions/road-runner" target="_blank">
        <img src="https://www.yiiframework.com/image/yii_logo_light.svg" alt="Yii Framework">
    </a>
    <h1 align="center">Extension for Road Runner</h1>
    <br>
</p>

<p align="center">
    <a href="https://www.php.net/releases/8.1/en.php" target="_blank">
        <img src="https://img.shields.io/badge/%3E%3D8.1-777BB4.svg?style=for-the-badge&logo=php&logoColor=white" alt="PHP version">
    </a>
    <a href="https://github.com/yiisoft/yii2/tree/2.0.53" target="_blank">
        <img src="https://img.shields.io/badge/2.0.x-0073AA.svg?style=for-the-badge&logo=yii&logoColor=white" alt="Yii 2.0.x">
    </a>
    <a href="https://github.com/yiisoft/yii2/tree/22.0" target="_blank">
        <img src="https://img.shields.io/badge/22.0.x-0073AA.svg?style=for-the-badge&logo=yii&logoColor=white" alt="Yii 22.0.x">
    </a>
    <a href="https://github.com/yii2-extensions/road-runner/actions/workflows/build.yml" target="_blank">
        <img src="https://img.shields.io/github/actions/workflow/status/yii2-extensions/road-runner/build.yml?style=for-the-badge&label=PHPUnit" alt="PHPUnit">
    </a>
    <a href="https://dashboard.stryker-mutator.io/reports/github.com/yii2-extensions/road-runner/main" target="_blank">
        <img src="https://img.shields.io/endpoint?style=for-the-badge&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fyii2-extensions%2Froad-runner%2Fmain" alt="Mutation testing badge">
    </a>
    <a href="https://github.com/yii2-extensions/road-runner/actions/workflows/static.yml" target="_blank">
        <img src="https://img.shields.io/github/actions/workflow/status/yii2-extensions/road-runner/static.yml?style=for-the-badge&label=PHPStan" alt="PHPStan">
    </a>
</p>

A high-performance RoadRunner integration for Yii2 applications that provides seamless PSR-7 request handling with 
automatic memory management and error reporting.

## Features

- ✅ **Automatic Memory Management**: Smart cleanup with configurable memory limits.
- ✅ **Error Handling**: Comprehensive error reporting to RoadRunner worker.
- ✅ **Graceful Shutdown**: Automatic worker restart when memory usage is high.
- ✅ **High Performance**: Utilize RoadRunner's blazing-fast HTTP server for your Yii2 applications.
- ✅ **Production Ready**: Battle-tested error handling and worker lifecycle management.
- ✅ **PSR-7 Compatible**: Full PSR-7 request/response handling through the PSR bridge.
- ✅ **Stateless Design**: Memory-efficient stateless application lifecycle.
- ✅ **Zero Configuration**: Works out of the box with minimal setup.

## Installation

```bash
composer require yii2-extensions/road-runner:^0.1.0@dev
```

### Basic Usage

Create your RoadRunner entry point (`web/index.php`)
```php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use yii2\extensions\psrbridge\http\StatelessApplication;
use yii2\extensions\roadrunner\RoadRunner;

// production default (change to 'true' for development)
defined('YII_DEBUG') or define('YII_DEBUG', false);
// production default (change to 'dev' for development)
defined('YII_ENV') or define('YII_ENV', 'prod');

require __DIR__ . '/../vendor/yiisoft/yii2/Yii.php';

$config = require dirname(__DIR__) . '/config/web.php';

$runner = new RoadRunner(new StatelessApplication($config));

$runner->run();
```

### RoadRunner configuration

Create `.rr.yaml` in your project root
```yaml
version: '3'
rpc:
    listen: 'tcp://127.0.0.1:6001'
server:
    command: 'php web/index.php'
    relay: pipes
http:
    address: '0.0.0.0:8080'
    # development-only overrides, remove or set to production values for deploys
    env:
        YII_DEBUG: true
        YII_ENV: dev    

    headers:
        response:
            "Cache-Control": "no-cache"

    middleware:
        - static   # serve files first
        - gzip     # compress dynamic output

    static:
        dir: web
        forbid:
            - .php
            - .htaccess
    pool:
        num_workers: 1
        supervisor:
            max_worker_memory: 100
jobs:
    pool:
        num_workers: 2
        max_worker_memory: 100
    consume: {  }

kv:
    local:
        driver: memory
        config:
            interval: 60
metrics:
    address: '127.0.0.1:2112'
```

### Start the server

```bash
# install RoadRunner binary
vendor/bin/rr get

# start the server
./rr serve
```

> Your applicaion will be available at `http://127.0.0.1:8080` (or `http://localhost:8080`) or at the address set in 
`http.address` in `.rr.yaml`.

### Development & Debugging

For enhanced debugging capabilities and proper time display in RoadRunner, install the worker debug extension.

```bash
composer require --dev yii2-extensions/worker-debug:^0.1
```

Add the following to your development configuration (`config/web.php`):

```php
<?php

declare(strict_types=1);

use yii2\extensions\debug\WorkerDebugModule;

if (YII_ENV_DEV) {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => WorkerDebugModule::class,
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];
}
```

### File Upload Handling

For enhanced file upload support in worker environments, use the PSR-7 bridge UploadedFile class instead of the standard 
Yii2 implementation.

```php
<?php

declare(strict_types=1);

use yii2\extensions\psrbridge\http\{Response, UploadedFile};

final class FileController extends \yii\web\Controller
{
    public function actionUpload(): Response
    {
        $file = UploadedFile::getInstanceByName('avatar');
        
        if ($file !== null && $file->error === UPLOAD_ERR_OK) {
            $file->saveAs('@webroot/uploads/' . $file->name);
        }
        
        return $this->asJson(['status' => 'uploaded']);
    }
}
```

## Documentation

For detailed configuration options and advanced usage.
- 📚 [Installation Guide](docs/installation.md)
- ⚙️ [Configuration Reference](docs/configuration.md) 
- 🧪 [Testing Guide](docs/testing.md)

## Package information

[![Development Status](https://img.shields.io/badge/Status-Dev-orange.svg?style=for-the-badge&logo=packagist&logoColor=white)](https://packagist.org/packages/yii2-extensions/road-runner)
[![Total Downloads](https://img.shields.io/packagist/dt/yii2-extensions/road-runner.svg?style=for-the-badge&logo=packagist&logoColor=white&label=Downloads)](https://packagist.org/packages/yii2-extensions/road-runner)

## Quality code

[![Codecov](https://img.shields.io/codecov/c/github/yii2-extensions/road-runner.svg?style=for-the-badge&logo=codecov&logoColor=white&label=Coverage)](https://codecov.io/github/yii2-extensions/road-runner)
[![PHPStan Level Max](https://img.shields.io/badge/PHPStan-Level%20Max-4F5D95.svg?style=for-the-badge&logo=php&logoColor=white)](https://github.com/yii2-extensions/road-runner/actions/workflows/static.yml)
[![StyleCI](https://img.shields.io/badge/StyleCI-Passed-44CC11.svg?style=for-the-badge&logo=styleci&logoColor=white)](https://github.styleci.io/repos/1029366421?branch=main)

## Our social networks

[![Follow on X](https://img.shields.io/badge/-Follow%20on%20X-1DA1F2.svg?style=for-the-badge&logo=x&logoColor=white&labelColor=000000)](https://x.com/Terabytesoftw)

## License

[![License](https://img.shields.io/github/license/yii2-extensions/road-runner?style=for-the-badge&logo=opensourceinitiative&logoColor=white&labelColor=333333)](LICENSE.md)
