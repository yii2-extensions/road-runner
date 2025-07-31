<?php

declare(strict_types=1);

namespace yii2\extensions\roadrunner\runtime;

use HttpSoft\Message\{ServerRequestFactory, StreamFactory, UploadedFileFactory};
use Psr\Http\Message\ServerRequestInterface;
use Spiral\RoadRunner\Http\PSR7Worker;
use Spiral\RoadRunner\Worker;
use Symfony\Component\Runtime\RunnerInterface;
use Throwable;
use yii2\extensions\psrbridge\http\StatelessApplication;

final class RoadRunner implements RunnerInterface
{
    private StatelessApplication $application;
    private PSR7Worker $worker;

    public function __construct(StatelessApplication $application)
    {
        $this->application = $application;
        $this->worker = new PSR7Worker(
            Worker::create(),
            new ServerRequestFactory(),
            new StreamFactory(),
            new UploadedFileFactory(),
        );
    }

    public function run(): int
    {
        try {
            // @phpstan-ignore-next-line
            while ($request = $this->worker->waitRequest()) {
                // @phpstan-ignore-next-line
                if ($request instanceof ServerRequestInterface) {
                    try {
                        $response = $this->application->handle($request);
                        $this->worker->respond($response);
                        if ($this->shouldRecycleWorker()) {
                            $this->worker->getWorker()->stop();

                            return 0;
                        }
                    } catch (Throwable $e) {
                        $this->handleRequestError($e);
                    }
                }
            }
        } catch (Throwable $e) {
            $this->handleFatalError($e);
            return 1;
        }

        return 0;
    }

    private function handleFatalError(Throwable $exception): void
    {
        $this->worker->getWorker()->error(
            sprintf(
                'Fatal error in RoadRunner worker: %s in %s:%d',
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine(),
            ),
        );
    }

    private function handleRequestError(Throwable $exception): void
    {
        $this->worker->getWorker()->error((string) $exception);
    }

    private function shouldRecycleWorker(): bool
    {
        return $this->application->clean();
    }
}
