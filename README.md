<p align="center">
    <a href="https://github.com/yii2-extensions/road-runner" target="_blank">
        <img src="https://www.yiiframework.com/image/yii_logo_light.svg" alt="Yii Framework">
    </a>
    <h1 align="center">Extension for Road Runner</h1>
    <br>
</p>

<p align="center">
    <a href="https://www.php.net/releases/8.1/en.php" target="_blank">
        <img src="https://img.shields.io/badge/PHP-%3E%3D8.1-787CB5" alt="PHP Version">
    </a>
    <a href="https://github.com/yiisoft/yii2/tree/2.0.53" target="_blank">
        <img src="https://img.shields.io/badge/Yii2%20-2.0.53-blue" alt="Yii2 2.0.53">
    </a>
    <a href="https://github.com/yiisoft/yii2/tree/22.0" target="_blank">
        <img src="https://img.shields.io/badge/Yii2%20-22-blue" alt="Yii2 22.0">
    </a>
    <a href="https://github.com/yii2-extensions/road-runner/actions/workflows/build.yml" target="_blank">
        <img src="https://github.com/yii2-extensions/road-runner/actions/workflows/build.yml/badge.svg" alt="PHPUnit">
    </a> 
    <a href="https://dashboard.stryker-mutator.io/reports/github.com/yii2-extensions/road-runner/main" target="_blank">
        <img src="https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fyii2-extensions%2Froad-runner%2Fmain" alt="Mutation Testing">
    </a>        
    <a href="https://github.com/yii2-extensions/road-runner/actions/workflows/static.yml" target="_blank">        
        <img src="https://github.com/yii2-extensions/road-runner/actions/workflows/static.yml/badge.svg" alt="Static Analysis">
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

## Quick start

### Installation

```bash
composer require yii2-extensions/road-runner
```

### Basic Usage

Create your RoadRunner entry point (`public/index.php`):

```php
<?php

declare(strict_types=1);

use yii2\extensions\psrbridge\http\StatelessApplication;
use yii2\extensions\roadrunner\RoadRunner;

defined('YII_DEBUG') or define('YII_DEBUG', true);
defined('YII_ENV') or define('YII_ENV', 'dev');

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../vendor/yiisoft/yii2/Yii.php';

$config = require dirname(__DIR__) . '/config/web/app.php';

$runner = new RoadRunner(new StatelessApplication($config));

$runner->run();
```

### RoadRunner configuration

Create `.rr.yaml` in your project root.

```yaml
version: '3'
server:
    command: "php ./public/index.php"

rpc:
    listen: tcp://127.0.0.1:6001

http:
    address: :8080
    pool:
        num_workers: 4
    timeout: 30s
    read_timeout: 30s
    write_timeout: 30s
    middleware: ["static", "headers"]
    static:
        dir:   "./public"
        forbid: [".php", ".htaccess"]
    headers:
        response:
            "Cache-Control": "no-cache"

logs:
    mode: development
    level: debug
```

### Start the server

```bash
# install RoadRunner binary
vendor/bin/rr get

# start the server
./rr serve
```

Your application will be available at `http://localhost:8080`.

## Documentation

For detailed configuration options and advanced usage.

- 📚 [Installation Guide](docs/installation.md)
- ⚙️ [Configuration Reference](docs/configuration.md) 
- 🧪 [Testing Guide](docs/testing.md)

## Quality code

[![Latest Stable Version](https://poser.pugx.org/yii2-extensions/road-runner/v)](https://github.com/yii2-extensions/road-runner/releases)
[![Total Downloads](https://poser.pugx.org/yii2-extensions/road-runner/downloads)](https://packagist.org/packages/yii2-extensions/road-runner)
[![codecov](https://codecov.io/gh/yii2-extensions/road-runner/graph/badge.svg?token=Upc4yA23YN)](https://codecov.io/gh/yii2-extensions/road-runner)
[![phpstan-level](https://img.shields.io/badge/PHPStan%20level-max-blue)](https://github.com/yii2-extensions/localeurls/actions/workflows/static.yml)
[![StyleCI](https://github.styleci.io/repos/698621511/shield?branch=main)](https://github.styleci.io/repos/698621511?branch=main)

## Our social networks

[![X](https://img.shields.io/badge/follow-@terabytesoftw-1DA1F2?logo=x&logoColor=1DA1F2&labelColor=555555&style=flat)](https://x.com/Terabytesoftw)

## License

[![License](https://img.shields.io/github/license/yii2-extensions/road-runner?cacheSeconds=0)](LICENSE.md)
