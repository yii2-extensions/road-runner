<?php

declare(strict_types=1);

namespace yii2\extensions\roadrunner\tests;

use HttpSoft\Message\{ServerRequest, Uri};
use PHPUnit\Framework\Attributes\Group;
use Psr\Http\Message\ResponseInterface;
use Spiral\RoadRunner\Http\PSR7Worker;
use Spiral\RoadRunner\WorkerInterface;
use yii\base\{Exception, InvalidConfigException};
use yii\console\ExitCode;
use yii\di\NotInstantiableException;
use yii2\extensions\roadrunner\RoadRunner;

use function ob_end_clean;
use function ob_get_level;

#[Group('roadrunner')]
final class RoadRunnerTest extends TestCase
{
    protected function tearDown(): void
    {
        $level = ob_get_level();

        while (--$level > 0) {
            ob_end_clean();
        }

        parent::tearDown();
    }

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
            ->expects(self::exactly(2))
            ->method('waitRequest')
            ->willReturnOnConsecutiveCalls($request, null);
        $this->psr7Worker
            ->expects(self::once())
            ->method('respond')
            ->with(self::isInstanceOf(ResponseInterface::class));
        $this->worker
            ->expects(self::once())
            ->method('stop');

        $app = $this->statelessApplication();

        // set a very low memory limit to force 'clean()' to return 'true', current memory usage will always be
        // '>= 90%' of '1' byte
        $app->setMemoryLimit(1);

        $roadRunner = new RoadRunner($app);

        self::assertSame(
            ExitCode::OK,
            $roadRunner->run(),
            "RoadRunner 'run()' method should return 'ExitCode::OK' after calling worker stop when application is " .
            'clean.',
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

        $app = $this->statelessApplication();

        // set a very high memory limit to force 'clean()' to return 'false', current memory usage will never be
        // '>= 90%' of 'PHP_INT_MAX'
        $app->setMemoryLimit(PHP_INT_MAX);

        $roadRunner = new RoadRunner($app);

        self::assertSame(
            ExitCode::OK,
            $roadRunner->run(),
            "RoadRunner 'run()' method should return 'ExitCode::OK' without calling worker stop when application is " .
            'not clean.',
        );
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

        // capture the exact error message passed to 'worker->error()'
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

        $app = $this->statelessApplication();

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

        $app = $this->statelessApplication();

        $roadRunner = new RoadRunner($app);
        $result = $roadRunner->run();

        self::assertSame(
            ExitCode::OK,
            $result,
            "RoadRunner 'run()' method should return 'ExitCode::OK' even when an exception occurs during request " .
            'handling.',
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

        $app = $this->statelessApplication();

        $roadRunner = new RoadRunner($app);

        self::assertSame(
            ExitCode::OK,
            $roadRunner->run(),
            "RoadRunner 'run()' method should return 'ExitCode::OK' after successfully handling a request.",
        );
    }

    /**
     * @throws InvalidConfigException if the configuration is invalid or incomplete.
     * @throws NotInstantiableException if a class or service can't be instantiated.
     */
    public function testRunMethodReturnsExitCodeOkWhenWorkerReturnsNull(): void
    {
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
            ->expects(self::once())
            ->method('waitRequest')
            ->willReturn(null);

        $app = $this->statelessApplication();

        $roadRunner = new RoadRunner($app);

        self::assertSame(
            ExitCode::OK,
            $roadRunner->run(),
            "RoadRunner 'run()' method should return 'ExitCode::OK' when worker returns 'null' indicating no more " .
            'requests.',
        );
    }
}
