<?php

declare(strict_types=1);

namespace yii2\extensions\roadrunner\tests\support;

use HttpSoft\Message\{ResponseFactory, ServerRequestFactory, StreamFactory, UploadedFileFactory};
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\{
    ResponseFactoryInterface,
    ServerRequestFactoryInterface,
    StreamFactoryInterface,
    UploadedFileFactoryInterface,
};
use Spiral\RoadRunner\Http\PSR7WorkerInterface;
use Spiral\RoadRunner\WorkerInterface;
use yii\caching\FileCache;
use yii\helpers\ArrayHelper;
use yii\log\FileTarget;
use yii\web\JsonParser;
use yii2\extensions\psrbridge\http\StatelessApplication;

use function dirname;

/**
 * Base test case providing common helpers and utilities for RoadRunner extension tests.
 *
 * Provides utilities to create Yii2 stateless application instances configured for RoadRunner testing environments.
 *
 * The test case sets up a pre-configured application with PSR-7 factories, caching, logging, and routing capabilities
 * suitable for testing RoadRunner integration with Yii2.
 *
 * Key features.
 * - Configures URL routing with pretty URLs and custom routing patterns.
 * - Creates `StatelessApplication` instances with a sane test configuration for RoadRunner.
 * - Pre-configures PSR-7 factories (ResponseFactory, ServerRequestFactory, StreamFactory, UploadedFileFactory).
 * - Provides file caching and logging components for test scenarios.
 * - Sets up RoadRunner PSR-7 worker and worker instances for dependency injection in tests.
 *
 * @copyright Copyright (C) 2025 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    /**
     * A secret key used for cookie validation in tests.
     */
    protected const COOKIE_VALIDATION_KEY = 'test-roadrunner-php';

    /**
     * RoadRunner PSR-7 worker instance for handling requests.
     */
    protected MockObject|PSR7WorkerInterface|null $psr7Worker = null;

    /**
     * RoadRunner worker instance for handling requests.
     */
    protected MockObject|WorkerInterface|null $worker = null;

    /**
     * @phpstan-param array{
     *   id?: string,
     *   basePath?: string,
     *   components?: array<string, array<string, mixed>>,
     *   container?: array{definitions?: array<string, mixed>},
     *   runtimePath?: string,
     *   vendorPath?: string
     * } $config
     */
    protected function statelessApplication(array $config = []): StatelessApplication
    {
        return new StatelessApplication(
            ArrayHelper::merge(
                [
                    'id' => 'stateless-app',
                    'basePath' => dirname(__DIR__, 2),
                    'bootstrap' => ['log'],
                    'components' => [
                        'cache' => [
                            'class' => FileCache::class,
                        ],
                        'log' => [
                            'traceLevel' => YII_DEBUG ? 3 : 0,
                            'targets' => [
                                [
                                    'class' => FileTarget::class,
                                    'levels' => [
                                        'error',
                                        'info',
                                        'warning',
                                    ],
                                ],
                            ],
                        ],
                        'request' => [
                            'cookieValidationKey' => self::COOKIE_VALIDATION_KEY,
                            'parsers' => [
                                'application/json' => JsonParser::class,
                            ],
                            'scriptFile' => __DIR__ . '/index.php',
                            'scriptUrl' => '/index.php',
                        ],
                        'urlManager' => [
                            'showScriptName' => false,
                            'enablePrettyUrl' => true,
                            'rules' => [
                                [
                                    'pattern' => '/<controller>/<action>/<test:\w+>',
                                    'route' => '<controller>/<action>',
                                ],
                            ],
                        ],
                    ],
                    'container' => [
                        'definitions' => [
                            PSR7WorkerInterface::class => fn(): MockObject|PSR7WorkerInterface|null => $this->psr7Worker,
                            ResponseFactoryInterface::class => ResponseFactory::class,
                            ServerRequestFactoryInterface::class => ServerRequestFactory::class,
                            StreamFactoryInterface::class => StreamFactory::class,
                            UploadedFileFactoryInterface::class => UploadedFileFactory::class,
                        ],
                    ],
                ],
                $config,
            ),
        );
    }
}
