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

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    protected MockObject|PSR7WorkerInterface|null $psr7Worker = null;
    protected MockObject|WorkerInterface|null $worker = null;
    /**
     * @phpstan-var array<mixed, mixed>
     */
    private array $originalServer = [];


    protected function setUp(): void
    {
        parent::setUp();

        $this->originalServer = $_SERVER;

        $_SERVER = [];
    }

    protected function tearDown(): void
    {
        $_COOKIE = [];
        $_FILES = [];
        $_GET = [];
        $_POST = [];
        $_SERVER = $this->originalServer;

        parent::tearDown();
    }

    /**
     * @phpstan-param array<string, mixed> $config
     */
    protected function statelessApplication($config = []): StatelessApplication
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
                            'enableCookieValidation' => false,
                            'enableCsrfCookie' => false,
                            'enableCsrfValidation' => false,
                            'parsers' => [
                                'application/json' => JsonParser::class,
                            ],
                            'scriptFile' => __DIR__ . '/index.php',
                            'scriptUrl' => '/index.php',
                        ],
                        'response' => [
                            'charset' => 'UTF-8',
                        ],
                        'user' => [
                            'enableAutoLogin' => false,
                        ],
                        'urlManager' => [
                            'showScriptName' => false,
                            'enableStrictParsing' => false,
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
