<?php

declare(strict_types=1);

namespace yii2\extensions\roadrunner\tests;

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

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    /**
     * A secret key used for cookie validation in tests.
     */
    protected const COOKIE_VALIDATION_KEY = 'wefJDF8sfdsfSDefwqdxj9oq';

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
                    'basePath' => __DIR__,
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
                                    'logFile' => '@runtime/log/app.log',
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
                    'runtimePath' => dirname(__DIR__) . '/runtime',
                    'vendorPath' => dirname(__DIR__) . '/vendor',
                ],
                $config,
            ),
        );
    }
}
