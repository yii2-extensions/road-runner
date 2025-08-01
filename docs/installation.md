# Installation guide

## System requirements

- [`PHP`](https://www.php.net/downloads) 8.1 or higher.
- [`Composer`](https://getcomposer.org/download/) for dependency management.
- [`RoadRunner`](https://github.com/roadrunner-server/roadrunner) 2024.3.0+.
- [`Yii2`](https://github.com/yiisoft/yii2) 2.0.53+ or 22.x.

## Installation

### Method 1: Using [Composer](https://getcomposer.org/download/) (recommended)

Install the extension.

```bash
composer require yii2-extensions/road-runner
```

### Method 2: Manual installation

Add to your `composer.json`.

```json
{
    "require": {
        "yii2-extensions/road-runner": "^0.1"
    }
}
```

Then run.

```bash
composer update
```

### Install RoadRunner binary

```bash
# Download the RoadRunner binary
./vendor/bin/rr get

# Or download manually from GitHub releases
curl -sSL https://github.com/roadrunner-server/roadrunner/releases/latest/download/roadrunner-linux-amd64.tar.gz | tar -xz
```

## Project structure

Organize your project for RoadRunner:

```
your-project/
├── public/
│   └── index.php          # RoadRunner entry point
├── .rr.yaml               # RoadRunner configuration
└── rr                     # RoadRunner binary
```

## Next steps

Once the installation is complete.

- ⚙️ [Configuration Reference](configuration.md)
- 🧪 [Testing Guide](testing.md)
