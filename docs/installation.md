# Installation guide

## System requirements

- [`PHP`](https://www.php.net/downloads) 8.1 or higher.
- [`Composer`](https://getcomposer.org/download/) for dependency management.
- [`RoadRunner`](https://github.com/roadrunner-server/roadrunner) 2025.1.2 or higher.
- [`Yii2`](https://github.com/yiisoft/yii2) 2.0.53+ or 22.x.

### PSR-7/PSR-17 HTTP Message Factories

- [`guzzlehttp/psr7`](https://github.com/guzzle/psr7)
- [`httpsoft/http-message`](https://github.com/httpsoft/http-message)
- [`nyholm/psr7`](https://github.com/Nyholm/psr7)

For example, install HttpSoft (recommended for Yii2 applications).

```bash
composer require httpsoft/http-message
```

## Installation

### Method 1: Using [Composer](https://getcomposer.org/download/) (recommended)

Install the extension.

```bash
composer require yii2-extensions/road-runner:^0.1.0@dev
```

### Method 2: Manual installation

Add to your `composer.json`.

```json
{
    "require": {
        "yii2-extensions/road-runner": "^0.1.0@dev"
    }
}
```

Then run.

```bash
composer update
```

### Install RoadRunner binary

```bash
# download the RoadRunner binary
vendor/bin/rr get

# or download manually from GitHub releases
curl -sSL https://github.com/roadrunner-server/roadrunner/releases/latest/download/roadrunner-linux-amd64.tar.gz | tar -xz
```

## Project structure

Organize your project for RoadRunner:

```text
app-basic/
├── web/
│   └── index.php          # RoadRunner entry point
├── .rr.yaml               # RoadRunner configuration
└── rr                     # RoadRunner binary
```

## Next steps

Once the installation is complete.

- ⚙️ [Configuration Reference](configuration.md)
- 🧪 [Testing Guide](testing.md)
