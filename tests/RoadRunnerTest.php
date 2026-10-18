<?php

declare(strict_types=1);

namespace yii2\extensions\roadrunner\tests;

use HttpSoft\Message\Response as PsrResponse;
use HttpSoft\Message\{ServerRequest, StreamFactory, UploadedFile, Uri};
use PHPUnit\Framework\Attributes\{Group, TestWith};
use Psr\Http\Message\{ResponseInterface, StreamInterface};
use Spiral\RoadRunner\Http\{PSR7Worker, PSR7WorkerInterface};
use Spiral\RoadRunner\WorkerInterface;
use WeakReference;
use Yii;
use yii\base\{Event, Exception, InvalidConfigException, Security};
use yii\console\ExitCode;
use yii\di\NotInstantiableException;
use yii2\extensions\psrbridge\http\{Application, Response};
use yii2\extensions\roadrunner\RoadRunner;
use yii2\extensions\roadrunner\tests\support\stub\ProbeApplication;
use yii2\extensions\roadrunner\tests\support\TestCase;

use function array_key_exists;

/**
 * Unit tests for the {@see RoadRunner} worker runtime integration.
 */
#[Group('roadrunner')]
final class RoadRunnerTest extends TestCase
{
    public function testRunMethodCallsWorkerStopWhenApplicationIsClean(): void
    {
        $request = new ServerRequest(
            serverParams: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/site/index',
            ],
            method: 'GET',
            uri: new Uri('http://localhost/'),
        );

        $this->worker = $this->createMock(WorkerInterface::class);
        $this->psr7Worker = $this->createPartialMock(
            PSR7Worker::class,
            [
                'waitRequest',
                'respond',
                'getWorker',
            ],
        );
        $this->psr7Worker
            ->method('getWorker')
            ->willReturn($this->worker);
        $this->psr7Worker
            ->expects(self::exactly(3))
            ->method('waitRequest')
            ->willReturnOnConsecutiveCalls($request, $request, null);
        $this->psr7Worker
            ->expects(self::once())
            ->method('respond')
            ->with(self::isInstanceOf(ResponseInterface::class));
        $this->worker
            ->expects(self::once())
            ->method('stop');

        $app = $this->application();

        // set a very low memory limit to force 'clean()' to return `true`, current memory usage will always be
        // '>= 90%' of '1' byte
        $app->setMemoryLimit(1);

        $roadRunner = new RoadRunner($app);

        self::assertSame(
            ExitCode::OK,
            $roadRunner->run(),
            "RoadRunner 'run()' method should return 'ExitCode::OK' after calling worker stop when application is "
            . 'clean.',
        );
    }

    public function testRunMethodChecksMemoryAfterEmissionFailure(): void
    {
        $request = new ServerRequest();

        $this->worker = $this->createMock(WorkerInterface::class);
        $this->psr7Worker = $this->createMock(PSR7WorkerInterface::class);

        $this->psr7Worker
            ->method('getWorker')
            ->willReturn($this->worker);
        $this->psr7Worker
            ->expects(self::exactly(3))
            ->method('waitRequest')
            ->willReturn($request, $request, null);
        $this->psr7Worker
            ->method('respond')
            ->willThrowException(new Exception('Emission failed.'));
        $this->worker
            ->expects(self::once())
            ->method('error');
        $this->worker
            ->expects(self::once())
            ->method('stop');

        $app = $this->application();

        $app->setMemoryLimit(1);

        self::assertSame(
            ExitCode::OK,
            (new RoadRunner($app))->run(),
            'Failed requests must also check worker memory.',
        );
    }

    public function testRunMethodCleansResponseLifecycleWhenRespondFails(): void
    {
        $afterSendEvents = [];

        Event::on(
            Response::class,
            Response::EVENT_AFTER_SEND,
            static function () use (&$afterSendEvents): void {
                $afterSendEvents[] = 'afterSend';
            },
        );

        self::assertTrue(
            Event::hasHandlers(Response::class, Response::EVENT_AFTER_SEND),
            'Test setup should register an after-send handler before RoadRunner handles the request.',
        );

        $request = new ServerRequest(
            serverParams: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/site/index',
            ],
            method: 'GET',
            uri: new Uri('http://localhost/'),
        );
        $exception = new Exception('Respond failed.');

        $this->worker = $this->createMock(WorkerInterface::class);
        $this->psr7Worker = $this->createPartialMock(
            PSR7Worker::class,
            [
                'waitRequest',
                'respond',
                'getWorker',
            ],
        );

        $this->psr7Worker
            ->method('getWorker')
            ->willReturn($this->worker);
        $this->psr7Worker
            ->expects(self::exactly(2))
            ->method('waitRequest')
            ->willReturnOnConsecutiveCalls($request, null);
        $this->psr7Worker
            ->expects(self::once())
            ->method('respond')
            ->willThrowException($exception);
        $this->worker
            ->expects(self::once())
            ->method('error')
            ->with(self::stringContains('Respond failed.'));

        $app = $this->application();

        $app->setMemoryLimit(PHP_INT_MAX);

        $roadRunner = new RoadRunner($app);

        self::assertSame(
            ExitCode::OK,
            $roadRunner->run(),
            "RoadRunner run method should return 'ExitCode::OK' after reporting response emission failure.",
        );
        self::assertSame(
            [],
            $afterSendEvents,
            'RoadRunner should clean lifecycle handlers without firing after-send when respond fails.',
        );
        self::assertFalse(
            Event::hasHandlers(Response::class, Response::EVENT_AFTER_SEND),
            'RoadRunner should remove response lifecycle event handlers when RoadRunner response emission fails.',
        );
        self::assertFalse(
            $app->response->isSent,
            'RoadRunner should not mark the Yii response sent when RoadRunner response emission fails.',
        );
    }

    public function testRunMethodCompletesResponseLifecycleAfterRespond(): void
    {
        $afterSendEvents = [];

        Event::on(
            Response::class,
            Response::EVENT_AFTER_SEND,
            static function () use (&$afterSendEvents): void {
                $afterSendEvents[] = 'afterSend';
            },
        );

        $request = new ServerRequest(
            serverParams: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/site/index',
            ],
            method: 'GET',
            uri: new Uri('http://localhost/'),
        );

        $this->worker = $this->createMock(WorkerInterface::class);

        $this->worker
            ->expects(self::never())
            ->method('error');

        $this->psr7Worker = $this->createPartialMock(
            PSR7Worker::class,
            [
                'waitRequest',
                'respond',
                'getWorker',
            ],
        );

        $this->psr7Worker
            ->method('getWorker')
            ->willReturn($this->worker);
        $this->psr7Worker
            ->expects(self::exactly(2))
            ->method('waitRequest')
            ->willReturnOnConsecutiveCalls($request, null);
        $this->psr7Worker
            ->expects(self::once())
            ->method('respond')
            ->with(self::isInstanceOf(ResponseInterface::class));

        $app = $this->application();

        $app->setMemoryLimit(PHP_INT_MAX);

        $roadRunner = new RoadRunner($app);

        self::assertSame(
            ExitCode::OK,
            $roadRunner->run(),
            "RoadRunner run method should return 'ExitCode::OK' after completing response lifecycle.",
        );
        self::assertSame(
            ['afterSend'],
            $afterSendEvents,
            'RoadRunner should complete Yii response lifecycle after responding with the PSR response.',
        );
    }

    public function testRunMethodContinuesAfterHandlingFailureAndFinalizesEachRequestOnce(): void
    {
        $request = new ServerRequest();

        $worker = $this->createMock(WorkerInterface::class);
        $psr7Worker = $this->createMock(PSR7WorkerInterface::class);

        $psr7Worker
            ->method('getWorker')
            ->willReturn($worker);
        $psr7Worker
            ->expects(self::exactly(3))
            ->method('waitRequest')
            ->willReturn($request, $request, null);
        $psr7Worker
            ->expects(self::once())
            ->method('respond');
        $worker
            ->expects(self::once())
            ->method('error')
            ->with(self::stringContains('Handling failed.'));

        Yii::$container->set(PSR7WorkerInterface::class, $psr7Worker);

        $app = $this->createPartialMock(Application::class, ['handle', 'finalize', 'clean']);

        $attempt = 0;
        $finalized = [];

        $app
            ->expects(self::exactly(2))
            ->method('handle')
            ->willReturnCallback(
                static function () use (&$attempt): ResponseInterface {
                    if ($attempt++ === 0) {
                        throw new Exception('Handling failed.');
                    }

                    return new PsrResponse();
                },
            );
        $app
            ->expects(self::exactly(2))
            ->method('finalize')
            ->willReturnCallback(
                static function (bool $success) use (&$finalized): void {
                    $finalized[] = $success;
                },
            );
        $app
            ->expects(self::exactly(2))
            ->method('clean')
            ->willReturn(false);

        self::assertSame(
            ExitCode::OK,
            (new RoadRunner($app))->run(),
            'The worker must accept the next request.',
        );
        self::assertSame(
            [false, true],
            $finalized,
            'Each attempt must finalize once with its emission result.',
        );
    }

    #[TestWith([true])]
    #[TestWith([false])]
    public function testRunMethodDeclinesPendingRequestsAfterFinalizationThrowsException(bool $success): void
    {
        $request = new ServerRequest();
        $failure = new Exception('Finalization failed.');

        $worker = $this->createMock(WorkerInterface::class);
        $psr7Worker = $this->createMock(PSR7WorkerInterface::class);

        $psr7Worker
            ->method('getWorker')
            ->willReturn($worker);
        $psr7Worker
            ->expects(self::exactly(4))
            ->method('waitRequest')
            ->willReturn($request, $request, $request, null);
        $psr7Worker
            ->expects($success ? self::once() : self::never())
            ->method('respond');
        $worker
            ->expects($success ? self::never() : self::once())
            ->method('error');
        $worker
            ->expects(self::exactly(2))
            ->method('stop');

        Yii::$container->set(PSR7WorkerInterface::class, $psr7Worker);

        $app = $this->createPartialMock(Application::class, ['handle', 'finalize', 'clean']);

        $handle = $app
            ->expects(self::once())
            ->method('handle');

        if ($success) {
            $handle->willReturn(new PsrResponse());
        } else {
            $handle->willThrowException(new Exception('Handling failed.'));
        }

        $app
            ->expects(self::once())
            ->method('finalize')
            ->with($success)
            ->willThrowException($failure);
        $app
            ->expects(self::never())
            ->method('clean');

        try {
            (new RoadRunner($app))->run();

            self::fail(
                'Finalization failures must propagate after the worker shutdown handshake.',
            );
        } catch (Exception $e) {
            self::assertSame(
                $failure,
                $e,
                'The runner must preserve the original finalization exception.',
            );
        }
    }

    public function testRunMethodDeclinesRepeatedDispatchesWhenMemoryLimitIsReached(): void
    {
        $request = new ServerRequest();

        $worker = $this->createMock(WorkerInterface::class);
        $psr7Worker = $this->createMock(PSR7WorkerInterface::class);

        $psr7Worker
            ->method('getWorker')
            ->willReturn($worker);
        $psr7Worker
            ->expects(self::exactly(4))
            ->method('waitRequest')
            ->willReturn($request, $request, $request, null);
        $psr7Worker
            ->expects(self::once())
            ->method('respond');
        $worker
            ->expects(self::never())
            ->method('error');
        $worker
            ->expects(self::exactly(2))
            ->method('stop');

        Yii::$container->set(PSR7WorkerInterface::class, $psr7Worker);

        $app = $this->createPartialMock(Application::class, ['handle', 'finalize', 'clean']);

        $app
            ->expects(self::once())
            ->method('handle')
            ->willReturn(new PsrResponse());
        $app
            ->expects(self::once())
            ->method('finalize')
            ->with(true);
        $app
            ->expects(self::once())
            ->method('clean')
            ->willReturn(true);

        self::assertSame(
            ExitCode::OK,
            (new RoadRunner($app))->run(),
            'Recycling must stop without handling new work.',
        );
    }

    public function testRunMethodDoesNotCallWorkerStopWhenApplicationIsNotClean(): void
    {
        $request = new ServerRequest(
            serverParams: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/site/index',
            ],
            method: 'GET',
            uri: new Uri('http://localhost/'),
        );

        $this->worker = $this->createMock(WorkerInterface::class);
        $this->psr7Worker = $this->createPartialMock(
            PSR7Worker::class,
            [
                'waitRequest',
                'respond',
                'getWorker',
            ],
        );
        $this->psr7Worker
            ->method('getWorker')
            ->willReturn($this->worker);
        $this->psr7Worker
            ->expects(self::exactly(2))
            ->method('waitRequest')
            ->willReturnOnConsecutiveCalls($request, null);
        $this->psr7Worker
            ->expects(self::once())
            ->method('respond')
            ->with(self::isInstanceOf(ResponseInterface::class));
        $this->worker
            ->expects(self::never())
            ->method('stop');

        $app = $this->application();

        // set a very high memory limit to force 'clean()' to return `false`, current memory usage will never be
        // '>= 90%' of 'PHP_INT_MAX'
        $app->setMemoryLimit(PHP_INT_MAX);

        $roadRunner = new RoadRunner($app);

        self::assertSame(
            ExitCode::OK,
            $roadRunner->run(),
            "RoadRunner 'run()' method should return 'ExitCode::OK' without calling worker stop when application is "
            . 'not clean.',
        );
    }

    public function testRunMethodDoesNotReportWorkerErrorWhenAfterSendFailsAfterRespond(): void
    {
        Event::on(
            Response::class,
            Response::EVENT_AFTER_SEND,
            static function (): void {
                throw new Exception('After-send failed.');
            },
        );

        $request = new ServerRequest(
            serverParams: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/site/index',
            ],
            method: 'GET',
            uri: new Uri('http://localhost/'),
        );

        $this->worker = $this->createMock(WorkerInterface::class);
        $this->psr7Worker = $this->createPartialMock(
            PSR7Worker::class,
            [
                'waitRequest',
                'respond',
                'getWorker',
            ],
        );

        $this->psr7Worker
            ->method('getWorker')
            ->willReturn($this->worker);
        $this->psr7Worker
            ->expects(self::exactly(3))
            ->method('waitRequest')
            ->willReturn($request, $request, null);
        $this->psr7Worker
            ->expects(self::once())
            ->method('respond')
            ->with(self::isInstanceOf(ResponseInterface::class));
        $this->worker
            ->expects(self::never())
            ->method('error');
        $this->worker
            ->expects(self::once())
            ->method('stop');

        $app = $this->application();

        $app->setMemoryLimit(PHP_INT_MAX);

        $roadRunner = new RoadRunner($app);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            'After-send failed.',
        );

        $roadRunner->run();
    }

    public function testRunMethodFinalizesWhenWorkerErrorReportingThrowsException(): void
    {
        $request = new ServerRequest();

        $worker = $this->createMock(WorkerInterface::class);
        $psr7Worker = $this->createMock(PSR7WorkerInterface::class);

        $psr7Worker
            ->method('getWorker')
            ->willReturn($worker);
        $psr7Worker
            ->expects(self::once())
            ->method('waitRequest')
            ->willReturn($request);
        $psr7Worker
            ->expects(self::never())
            ->method('respond');
        $worker
            ->expects(self::once())
            ->method('error')
            ->willThrowException(new Exception('Reporting failed.'));

        Yii::$container->set(PSR7WorkerInterface::class, $psr7Worker);

        $app = $this->createPartialMock(Application::class, ['handle', 'finalize', 'clean']);

        $app
            ->expects(self::once())
            ->method('handle')
            ->willThrowException(new Exception('Handling failed.'));
        $app
            ->expects(self::once())
            ->method('finalize')->with(false);
        $app
            ->expects(self::never())
            ->method('clean');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            'Reporting failed.',
        );

        (new RoadRunner($app))->run();
    }

    public function testRunMethodFormatsErrorMessageCorrectly(): void
    {
        $request = new ServerRequest(
            serverParams: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/site/index',
            ],
            method: 'GET',
            uri: new Uri('http://localhost/'),
        );

        // create a custom exception with known values for testing
        $testException = new Exception('Test error message.');

        $this->worker = $this->createMock(WorkerInterface::class);
        $this->psr7Worker = $this->createPartialMock(
            PSR7Worker::class,
            [
                'waitRequest',
                'respond',
                'getWorker',
            ],
        );
        $this->psr7Worker
            ->method('getWorker')
            ->willReturn($this->worker);
        $this->psr7Worker
            ->expects(self::exactly(2))
            ->method('waitRequest')
            ->willReturnOnConsecutiveCalls($request, null);
        $this->psr7Worker
            ->expects(self::once())
            ->method('respond')
            ->willThrowException($testException);

        // capture the exact error message passed to `worker->error()`
        $expectedErrorPattern = sprintf(
            "['%s'] '%s' in '%s:%d'\nStack trace:\n'%s'",
            Exception::class,
            'Test error message.',
            $testException->getFile(),
            $testException->getLine(),
            $testException->getTraceAsString(),
        );

        $this->worker
            ->expects(self::once())
            ->method('error')
            ->with(self::identicalTo($expectedErrorPattern));

        $app = $this->application();

        $roadRunner = new RoadRunner($app);

        self::assertSame(
            ExitCode::OK,
            $roadRunner->run(),
            "RoadRunner 'run()' method should return 'ExitCode::OK' and format error message correctly.",
        );
    }

    public function testRunMethodHandlesExceptionDuringRequestProcessing(): void
    {
        $request = new ServerRequest(
            serverParams: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/',
            ],
            method: 'GET',
            uri: new Uri('http://localhost/'),
        );

        $this->worker = $this->createMock(WorkerInterface::class);
        $this->psr7Worker = $this->createPartialMock(
            PSR7Worker::class,
            [
                'waitRequest',
                'respond',
                'getWorker',
            ],
        );
        $this->psr7Worker
            ->method('getWorker')
            ->willReturn($this->worker);
        $this->psr7Worker
            ->expects(self::exactly(2))
            ->method('waitRequest')
            ->willReturnOnConsecutiveCalls($request, null);
        $this->psr7Worker
            ->expects(self::once())
            ->method('respond')
            ->willThrowException(new Exception('An error occurred during request processing.'));
        $this->worker
            ->expects(self::once())
            ->method('error')
            ->with(self::stringContains('An error occurred during request processing.'));

        $app = $this->application();

        $roadRunner = new RoadRunner($app);
        $result = $roadRunner->run();

        self::assertSame(
            ExitCode::OK,
            $result,
            "RoadRunner 'run()' method should return 'ExitCode::OK' even when an exception occurs during request "
            . 'handling.',
        );
    }

    public function testRunMethodHandlesSingleRequestSuccessfully(): void
    {
        $request = new ServerRequest(
            serverParams: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/site/index',
            ],
            method: 'GET',
            uri: new Uri('http://localhost/'),
        );

        $this->worker = $this->createMock(WorkerInterface::class);

        $this->worker
            ->expects(self::never())
            ->method('error');

        $this->psr7Worker = $this->createPartialMock(
            PSR7Worker::class,
            [
                'waitRequest',
                'respond',
                'getWorker',
            ],
        );

        $this->psr7Worker
            ->method('getWorker')
            ->willReturn($this->worker);
        $this->psr7Worker
            ->expects(self::exactly(2))
            ->method('waitRequest')
            ->willReturnOnConsecutiveCalls(
                $request,
                null,
            );
        $this->psr7Worker
            ->expects(self::once())
            ->method('respond')
            ->with(self::isInstanceOf(ResponseInterface::class));

        $app = $this->application();

        $roadRunner = new RoadRunner($app);

        self::assertSame(
            ExitCode::OK,
            $roadRunner->run(),
            "RoadRunner 'run()' method should return 'ExitCode::OK' after successfully handling a request.",
        );
    }

    public function testRunMethodIsolatesSessionIdsAndUploadsBetweenRequests(): void
    {
        $sessionName = session_name();

        self::assertIsString($sessionName, 'PHP must provide a session cookie name.');

        $security = new Security();
        $signedCookie = static fn(string $id): string => $security->hashData(
            serialize([$sessionName, $id]),
            self::COOKIE_VALIDATION_KEY,
        );
        $request = new ServerRequest(method: 'GET', uri: new Uri('http://localhost/'));

        $requests = [
            $request
                ->withCookieParams([$sessionName => $signedCookie('visitor-a')])
                ->withUploadedFiles(
                    [
                        'file' => new UploadedFile(
                            (new StreamFactory())->createStream('contents'),
                            8,
                            UPLOAD_ERR_OK,
                            'first.txt',
                        ),
                    ]
                ),
            $request->withCookieParams([$sessionName => $signedCookie('visitor-b')]),
            $request,
            $request->withCookieParams([$sessionName => $signedCookie('invalid/id')]),
            $request->withCookieParams([$sessionName => 'tampered-cookie']),
        ];
        $emitted = [];

        $this->worker = $this->createMock(WorkerInterface::class);
        $this->psr7Worker = $this->createMock(PSR7WorkerInterface::class);

        $this->psr7Worker
            ->method('getWorker')
            ->willReturn($this->worker);
        $this->psr7Worker
            ->expects(self::exactly(6))
            ->method('waitRequest')
            ->willReturn(...[...$requests, null]);
        $this->psr7Worker
            ->expects(self::exactly(5))
            ->method('respond')
            ->willReturnCallback(
                static function (ResponseInterface $response) use (&$emitted): void {
                    self::assertSame(200, $response->getStatusCode(), 'The bridge must prepare a successful response.');
                    $emitted[] = (string) $response->getBody();
                },
            );
        $this->worker
            ->expects(self::never())
            ->method('error');

        $app = $this->application(['useSession' => false], ProbeApplication::class);

        $app->setMemoryLimit(PHP_INT_MAX);

        self::assertSame(
            ExitCode::OK,
            (new RoadRunner($app))->run(),
            'The runner must process every visitor.',
        );
        self::assertSame(
            [
                '{"sessionId":"visitor-a","upload":"first.txt"}',
                '{"sessionId":"visitor-b","upload":null}',
                '{"sessionId":"","upload":null}',
                '{"sessionId":"","upload":null}',
                '{"sessionId":"","upload":null}',
            ],
            $emitted,
            'Session selection and cached uploads must belong to the current request even with lazy sessions.',
        );
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function testRunMethodPreservesExceptionArgumentsUntilFailureIsReleased(bool $ignoreArguments): void
    {
        $previous = ini_set('zend.exception_ignore_args', $ignoreArguments ? '1' : '0');

        self::assertNotFalse(
            $previous,
            'The test must control exception argument capture.',
        );

        $references = new class {
            /**
             * @var WeakReference<StreamInterface>|null
             */
            public WeakReference|null $body = null;
        };

        $transport = $this->createMock(WorkerInterface::class);

        $transport
            ->method('getPayload')
            ->willReturn(null);
        $transport
            ->expects(self::never())
            ->method('error');
        $transport
            ->expects(self::once())
            ->method('stop');

        $waits = 0;

        $wait = static function () use (&$waits, $references, $ignoreArguments): ServerRequest|null {
            if (++$waits > 1) {
                gc_collect_cycles();
                self::assertSame(
                    !$ignoreArguments,
                    $references->body?->get() !== null,
                    'Pending exception arguments retain the body only when argument capture is enabled.',
                );
            }

            return $waits < 3 ? new ServerRequest() : null;
        };
        $worker = new class ($transport, $wait) implements PSR7WorkerInterface {
            private readonly \Spiral\RoadRunner\Http\HttpWorker $httpWorker;

            /**
             * @param \Closure(): (ServerRequest|null) $wait
             */
            public function __construct(private readonly WorkerInterface $transport, private readonly \Closure $wait)
            {
                $this->httpWorker = new \Spiral\RoadRunner\Http\HttpWorker($transport);
            }

            public function getWorker(): WorkerInterface
            {
                return $this->transport;
            }

            public function waitRequest(): ServerRequest|null
            {
                return ($this->wait)();
            }

            public function respond(ResponseInterface $response): void
            {
                $chunks = static function (StreamInterface $body): \Generator {
                    yield $body->read(3);

                    return $body->read(3);
                };
                $this->httpWorker->respond(
                    $response->getStatusCode(),
                    $chunks($response->getBody()),
                    $response->getHeaders(),
                );
            }
        };

        Yii::$container->set(PSR7WorkerInterface::class, $worker);

        $app = $this->createPartialMock(Application::class, ['handle', 'finalize', 'clean']);

        $app
            ->expects(self::once())
            ->method('handle')
            ->willReturnCallback(
                static function () use ($references): ResponseInterface {
                    $body = new \yii2\extensions\roadrunner\tests\support\stub\FailingReadStream();
                    $references->body = WeakReference::create($body);

                    return (new PsrResponse())->withBody($body);
                },
            );
        $app
            ->expects(self::once())
            ->method('finalize')
            ->with(false);
        $app
            ->expects(self::never())
            ->method('clean');

        try {
            try {
                (new RoadRunner($app))->run();

                self::fail(
                    'The original stream failure must propagate after recycling.',
                );
            } catch (\RuntimeException $e) {
                self::assertSame(
                    'Stream read failed.',
                    $e->getMessage(),
                    'The read failure must remain observable.',
                );
            }

            unset($e);
            gc_collect_cycles();

            self::assertNull(
                $references->body?->get(),
                'Releasing the exception must release its retained body.',
            );
        } finally {
            ini_set('zend.exception_ignore_args', $previous);
        }
    }

    #[TestWith([true])]
    #[TestWith([false])]
    public function testRunMethodPreservesFinalizationExceptionWhenShutdownThrowsException(bool $waitFails): void
    {
        $request = new ServerRequest();
        $failure = new Exception('Finalization failed.');
        $transport = new Exception('Shutdown failed.');

        $worker = $this->createMock(WorkerInterface::class);
        $psr7Worker = $this->createMock(PSR7WorkerInterface::class);

        $attempt = 0;

        $psr7Worker
            ->method('getWorker')
            ->willReturn($worker);
        $psr7Worker
            ->expects(self::exactly(2))
            ->method('waitRequest')
            ->willReturnCallback(
                static function () use (&$attempt, $request, $transport, $waitFails): ServerRequest {
                    if ($attempt++ > 0 && $waitFails) {
                        throw $transport;
                    }

                    return $request;
                },
            );
        $psr7Worker
            ->expects(self::once())
            ->method('respond');
        $worker
            ->expects(self::never())
            ->method('error');
        $worker
            ->expects($waitFails ? self::never() : self::once())
            ->method('stop')
            ->willThrowException($transport);

        Yii::$container->set(PSR7WorkerInterface::class, $psr7Worker);

        $app = $this->createPartialMock(Application::class, ['handle', 'finalize', 'clean']);

        $app
            ->expects(self::once())
            ->method('handle')
            ->willReturn(new PsrResponse());
        $app
            ->expects(self::once())
            ->method('finalize')
            ->with(true)
            ->willThrowException($failure);
        $app
            ->expects(self::never())
            ->method('clean');

        try {
            (new RoadRunner($app))->run();

            self::fail(
                'The original finalization failure must propagate when shutdown also fails.',
            );
        } catch (Exception $e) {
            self::assertSame(
                $failure,
                $e,
                'Shutdown failure must not replace the finalization exception.',
            );
            self::assertSame(
                $transport,
                $e->getPrevious(),
                'The shutdown exception must remain available as the cause.',
            );
        }
    }

    public function testRunMethodPreservesReportingExceptionWhenFinalizationThrowsException(): void
    {
        $handling = new Exception('Handling failed.');
        $reporting = new Exception('Reporting failed.');
        $finalization = new Exception('Finalization failed.');

        $transport = $this->createMock(WorkerInterface::class);

        $transport
            ->expects(self::once())
            ->method('error')
            ->with(self::stringContains('Handling failed.'))
            ->willThrowException($reporting);
        $transport
            ->expects(self::never())
            ->method('stop');

        $worker = $this->createMock(PSR7WorkerInterface::class);

        $worker
            ->method('getWorker')
            ->willReturn($transport);
        $worker
            ->expects(self::once())
            ->method('waitRequest')
            ->willReturn(new ServerRequest());
        $worker
            ->expects(self::never())
            ->method('respond');

        Yii::$container->set(PSR7WorkerInterface::class, $worker);

        $app = $this->createPartialMock(Application::class, ['handle', 'finalize', 'clean']);

        $app
            ->method('handle')
            ->willThrowException($handling);
        $app
            ->expects(self::once())
            ->method('finalize')
            ->with(false)
            ->willThrowException($finalization);
        $app
            ->expects(self::never())
            ->method('clean');

        try {
            (new RoadRunner($app))->run();

            self::fail(
                'The finalization failure must propagate.',
            );
        } catch (Exception $e) {
            self::assertSame(
                $finalization,
                $e,
                'The finalization exception must remain primary.',
            );
            self::assertSame(
                $reporting,
                $e->getPrevious(),
                'The pending reporting failure must be preserved.',
            );
        }
    }


    /**
     * @param 0|1|2 $failAt
     */
    #[TestWith([0, 'read', false])]
    #[TestWith([1, 'read', false])]
    #[TestWith([2, 'read', false])]
    #[TestWith([2, 'eof', false])]
    #[TestWith([2, 'read', true])]
    #[TestWith([2, 'read', false, true])]
    #[TestWith([2, 'read', true, true])]
    #[TestWith([2, 'read', false, false, true])]
    #[TestWith([2, 'read', true, false, true])]
    #[TestWith([2, 'eof', false, false, true])]
    public function testRunMethodPreservesStreamingFailures(
        int $failAt,
        string $operation,
        bool $finalizationFails,
        bool $shutdownFails = false,
        bool $closingFrameFails = false,
    ): void {
        $readFailure = new Exception('Stream read failed.');
        $finalizationFailure = new Exception('Finalization failed.');
        $shutdownFailure = new Exception('Shutdown failed.');
        $closingFailure = new Exception('Closing frame failed.');

        $stream = self::createStub(StreamInterface::class);

        $stream
            ->method('getSize')
            ->willReturn(6);

        $reads = 0;

        $stream
            ->method('eof')
            ->willReturnCallback(
                static function () use ($operation, $readFailure): bool {
                    if ($operation === 'eof') {
                        throw $readFailure;
                    }

                    return false;
                },
            );
        $stream
            ->method('read')
            ->willReturnCallback(
                static function (int $length) use (&$reads, $operation, $failAt, $readFailure): string {
                    self::assertSame(3, $length, 'The real emitter must read the configured chunk size.');

                    if (++$reads === $failAt && $operation === 'read') {
                        throw $readFailure;
                    }

                    return $reads === 1 ? 'abc' : 'def';
                },
            );

        $lateFailure = $failAt === 2;

        $transport = $this->createMock(WorkerInterface::class);

        $stop = $transport
            ->expects($lateFailure && !$closingFrameFails ? self::once() : self::never())
            ->method('stop');

        if ($shutdownFails) {
            $stop->willThrowException($shutdownFailure);
        }

        $transport
            ->expects($failAt === 1 ? self::once() : self::never())
            ->method('error');

        $payloads = [];

        $transport
            ->method('respond')
            ->willReturnCallback(
                static function (object $payload) use (&$payloads, $closingFrameFails, $closingFailure): void {
                    $properties = get_object_vars($payload);

                    if (array_key_exists('body', $properties) === false) {
                        self::fail(
                            'Transport payloads must expose their body.',
                        );
                    }

                    $body = $properties['body'];
                    self::assertIsString($body, 'Transport payload bodies must remain strings.');
                    $payloads[] = $body;

                    if ($closingFrameFails && $body === '') {
                        throw $closingFailure;
                    }
                },
            );
        $worker = $this->getMockBuilder(PSR7Worker::class)
            ->setConstructorArgs(
                [
                    $transport,
                    new \HttpSoft\Message\ServerRequestFactory(),
                    new StreamFactory(),
                    new \HttpSoft\Message\UploadedFileFactory(),
                ],
            )
            ->onlyMethods(['waitRequest', 'respond'])
            ->getMock();
        $worker
            ->method('respond')
            ->willReturnCallback(
                static function (ResponseInterface $response) use ($worker): void {
                    $chunks = static function () use ($response): \Generator {
                        $body = $response->getBody();

                        yield $body->read(3);

                        if ($body->eof()) {
                            return '';
                        }

                        return $body->read(3);
                    };
                    $worker->getHttpWorker()->respond($response->getStatusCode(), $chunks(), $response->getHeaders());
                },
            );

        $request = new ServerRequest();

        $worker
            ->expects(self::exactly($closingFrameFails ? 1 : ($lateFailure && !$shutdownFails ? 3 : 2)))
            ->method('waitRequest')
            ->willReturn(
                ...($lateFailure ? [$request, $request, null] : [$request, null]),
            );

        Yii::$container->set(PSR7WorkerInterface::class, $worker);

        $app = $this->createPartialMock(Application::class, ['handle', 'finalize', 'clean']);

        $app
            ->expects(self::once())
            ->method('handle')
            ->willReturn((new PsrResponse())
            ->withBody($stream));

        $finalizer = $app
            ->expects(self::once())
            ->method('finalize')
            ->with($failAt === 0);

        if ($finalizationFails) {
            $finalizer->willThrowException($finalizationFailure);
        }

        $app
            ->expects($lateFailure ? self::never() : self::once())
            ->method('clean')
            ->willReturn(false);

        try {
            self::assertSame(
                ExitCode::OK,
                (new RoadRunner($app))->run(),
                'Completed attempts must exit normally.',
            );
            self::assertFalse(
                $lateFailure,
                'A swallowed streaming failure must propagate after shutdown.',
            );
        } catch (Exception $e) {
            self::assertTrue(
                $lateFailure,
                'Only a failure after emission may require deferred propagation.',
            );
            self::assertSame(
                $finalizationFails ? $finalizationFailure : $readFailure,
                $e,
                'The original failure must survive the shutdown handshake.',
            );

            if ($finalizationFails) {
                self::assertSame(
                    $readFailure,
                    $e->getPrevious(),
                    'Finalization must preserve the stream failure.',
                );
            }

            if ($closingFrameFails) {
                self::assertSame(
                    $closingFailure,
                    $readFailure->getPrevious(),
                    'The closing transport failure must remain in the original read exception chain.',
                );
            }

            if ($shutdownFails) {
                self::assertSame(
                    $shutdownFailure,
                    $finalizationFails ? $readFailure->getPrevious() : $e->getPrevious(),
                    'The exception chain must retain shutdown failures as well as stream failures.',
                );
            }
        }

        self::assertSame(
            match ($failAt) {
                0 => ['abc', 'def'],
                1 => [],
                2 => ['abc', ''],
            },
            $payloads,
            'Failure after a chunk must not submit a second response outcome.',
        );
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function testRunMethodReleasesResponseBeforeWaitingForNextRequest(bool $emissionFails): void
    {
        $references = new class {
            /**
             * @var WeakReference<object>|null
             */
            public WeakReference|null $response = null;
            /**
             * @var WeakReference<object>|null
             */
            public WeakReference|null $body = null;
            /**
             * @var WeakReference<object>|null
             */
            public WeakReference|null $wrapped = null;
        };

        $calls = 0;

        $transport = $this->createMock(WorkerInterface::class);

        $transport
            ->expects($emissionFails ? self::once() : self::never())
            ->method('error');
        $wait = static function () use (&$calls, $references): ServerRequest|null {
            if ($calls++ === 0) {
                return new ServerRequest();
            }

            self::assertNull(
                $references->response?->get(),
                'The completed response must be released before waiting.',
            );
            self::assertNull(
                $references->body?->get(),
                'The completed body must be released before waiting.',
            );
            self::assertNull(
                $references->wrapped?->get(),
                'The emission wrapper must be released before waiting.',
            );

            return null;
        };
        $respond
            = static function (ResponseInterface $response) use ($references, $emissionFails): void {
                $references->wrapped = WeakReference::create($response->getBody());

                if ($emissionFails) {
                    throw new Exception(
                        'Emission failed.',
                    );
                }
            };
        $worker = new class ($transport, $wait, $respond) implements PSR7WorkerInterface {
            /**
             * @param \Closure(): (ServerRequest|null) $wait
             * @param \Closure(ResponseInterface): void $respond
             */
            public function __construct(
                private readonly WorkerInterface $transport,
                private readonly \Closure $wait,
                private readonly \Closure $respond,
            ) {}

            public function getWorker(): WorkerInterface
            {
                return $this->transport;
            }

            public function waitRequest(): ServerRequest|null
            {
                return ($this->wait)();
            }

            public function respond(ResponseInterface $response): void
            {
                ($this->respond)($response);
            }
        };

        Yii::$container->set(PSR7WorkerInterface::class, $worker);

        $app = $this->createPartialMock(Application::class, ['handle', 'finalize', 'clean']);

        $app
            ->method('handle')
            ->willReturnCallback(
                static function () use ($references): ResponseInterface {
                    $body = (new StreamFactory())->createStream('response');
                    $response = (new PsrResponse())->withBody($body);
                    $references->body = WeakReference::create($body);
                    $references->response = WeakReference::create($response);

                    return $response;
                },
            );
        $app
            ->expects(self::once())
            ->method('finalize')
            ->with(!$emissionFails);
        $app
            ->method('clean')
            ->willReturn(false);

        self::assertSame(
            ExitCode::OK,
            (new RoadRunner($app))->run(),
            'The next dispatch must remain available.',
        );
    }

    /**
     * @throws InvalidConfigException if the configuration is invalid or incomplete.
     * @throws NotInstantiableException if a class or service can't be instantiated.
     */
    public function testRunMethodReturnsExitCodeOkWhenWorkerReturnsNull(): void
    {
        $this->worker = $this->createMock(WorkerInterface::class);

        $this->worker
            ->expects(self::never())
            ->method('error');
        $this->psr7Worker = $this->createPartialMock(
            PSR7Worker::class,
            [
                'waitRequest',
                'respond',
                'getWorker',
            ],
        );
        $this->psr7Worker
            ->method('getWorker')
            ->willReturn($this->worker);
        $this->psr7Worker
            ->expects(self::once())
            ->method('waitRequest')
            ->willReturn(null);

        $app = $this->application();

        $roadRunner = new RoadRunner($app);

        self::assertSame(
            ExitCode::OK,
            $roadRunner->run(),
            "RoadRunner 'run()' method should return 'ExitCode::OK' when worker returns 'null' indicating no more "
            . 'requests.',
        );
    }
}
