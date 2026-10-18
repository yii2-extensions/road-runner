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
use Yii;
use yii\base\{ErrorHandler, Event};
use yii\caching\FileCache;
use yii\di\Container;
use yii\helpers\ArrayHelper;
use yii\log\FileTarget;
use yii\web\{IdentityInterface, JsonParser, Session};
use yii2\extensions\psrbridge\http\Application;

use function dirname;

/**
 * Base class for package integration tests.
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
     * Creates an integration-test application with default components and optional overrides.
     *
     * @phpstan-param array{
     *   id?: string,
     *   useSession?: bool,
     *   basePath?: string,
     *   components?: array<string, array<string, mixed>>,
     *   container?: array{definitions?: array<string, mixed>},
     *   runtimePath?: string,
     *   vendorPath?: string
     * } $config
     * @phpstan-param class-string<Application<IdentityInterface>> $applicationClass
     * @phpstan-return Application<IdentityInterface>
     */
    protected function application(array $config = [], string $applicationClass = Application::class): Application
    {
        /** @phpstan-var array<string, mixed> $configApplication */
        $configApplication = ArrayHelper::merge(
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
        );

        return new $applicationClass($configApplication);
    }

    protected function tearDown(): void
    {
        if (Yii::$app !== null) {
            $session = Yii::$app->get('session', false);
            $errorHandler = Yii::$app->get('errorHandler', false);

            if ($session instanceof Session) {
                $session->close();
            }

            if ($errorHandler instanceof ErrorHandler) {
                $errorHandler->unregister();
            }
        }

        Event::offAll();

        Yii::$container = new Container();

        parent::tearDown();
    }
}
