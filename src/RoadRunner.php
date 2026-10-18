<?php

declare(strict_types=1);

namespace yii2\extensions\roadrunner;

use Spiral\RoadRunner\Http\PSR7WorkerInterface;
use Throwable;
use Yii;
use yii\web\IdentityInterface;
use yii2\extensions\psrbridge\http\{Application, ServerExitCode};

use function sprintf;

/**
 * Runs the RoadRunner request loop for a Yii PSR bridge application.
 *
 * @see Application for the PSR bridge application implementation.
 */
final class RoadRunner
{
    /**
     * Creates a new instance of the {@see RoadRunner} class.
     *
     * @param Application $app PSR bridge application instance.
     *
     * @phpstan-param Application<IdentityInterface> $app
     */
    public function __construct(private readonly Application $app) {}

    /**
     * Processes requests from the configured {@see Application} instance until the worker returns `null`.
     *
     * Reports failures through `error()` only while the response has not been emitted. Finalization runs once in
     * `finally`, passing `false` when processing or emission fails. Memory is checked after either outcome.
     *
     * Recycling declines the next dispatch before handling it, allowing RoadRunner to retry it in a fresh worker.
     * Streaming read and finalization failures propagate after this handshake without a second request outcome;
     * any transport failure during shutdown is retained as the previous exception. A further emission failure
     * while closing an already-failed stream propagates immediately after finalization without another outcome.
     *
     * @throws Throwable When streaming, worker transport, or application finalization fails.
     *
     * @return int Exit code indicating successful execution ({@see ServerExitCode::OK}).
     */
    public function run(): int
    {
        $worker = Yii::$container->get(PSR7WorkerInterface::class);

        $recycle = false;
        $failure = null;

        try {
            while (($request = $worker->waitRequest()) !== null) {
                if ($recycle) {
                    $worker->getWorker()->stop();

                    continue;
                }

                $success = false;

                try {
                    $response = $this->app->handle($request);

                    $body = new ResponseBody($response->getBody());

                    $worker->respond($response->withBody($body));

                    $failure = $body->getFailure();
                    $success = $failure === null;
                } catch (Throwable $e) {
                    $readFailure = isset($body) ? $body->getFailure() : null;

                    if ($readFailure !== null && $readFailure !== $e) {
                        // Emission failed again while closing a stream whose read failure was consumed.
                        $failure = $readFailure;

                        throw $e;
                    }

                    $error = sprintf(
                        "['%s'] '%s' in '%s:%d'\nStack trace:\n'%s'",
                        $e::class,
                        $e->getMessage(),
                        $e->getFile(),
                        $e->getLine(),
                        $e->getTraceAsString(),
                    );

                    $worker->getWorker()->error($error);
                } finally {
                    try {
                        // Keep a consumed read failure pending while finalizing.
                        try {
                            if ($failure !== null) {
                                throw $failure;
                            }
                        } finally {
                            $this->app->finalize($success);
                        }
                    } catch (Throwable $e) {
                        $failure = $e;
                        $recycle = true;
                    }

                    unset($response, $body, $e, $readFailure);
                }

                if ($failure === null) {
                    $recycle = $this->app->clean();
                }
            }
        } finally {
            if ($failure !== null) {
                throw $failure;
            }
        }

        return ServerExitCode::OK->value;
    }
}
