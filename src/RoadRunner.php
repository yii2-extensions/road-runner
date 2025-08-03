<?php

declare(strict_types=1);

namespace yii2\extensions\roadrunner;

use Spiral\RoadRunner\Http\PSR7WorkerInterface;
use Throwable;
use yii2\extensions\psrbridge\http\{ServerExitCode, StatelessApplication};

use function sprintf;

/**
 * RoadRunner runtime integration for Yii2 Stateless Application.
 *
 * Provides a request loop for handling PSR-7 requests from a RoadRunner worker, delegating processing to a
 * {@see StatelessApplication} instance and emitting PSR-7 responses.
 *
 * This class manages the lifecycle of the RoadRunner worker, including request handling, response emission, and
 * automatic shutdown when the application state is clean.
 *
 * All exceptions are caught and reported to the worker for error handling.
 *
 * Key features.
 * - Ensures clean shutdown and error reporting to the RoadRunner worker.
 * - Executes the RoadRunner request loop for stateless Yii2 applications.
 * - Handles incoming PSR-7 requests and emits PSR-7 responses.
 * - Integrates with the application container for worker instantiation.
 *
 * @see StatelessApplication for the Stateless Application implementation.
 *
 * @copyright Copyright (C) 2025 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
final class RoadRunner
{
    /**
     * RoadRunner PSR-7 worker instance for handling requests.
     */
    private PSR7WorkerInterface $worker;

    /**
     * Creates a new instance of the {@see RoadRunner} class.
     *
     * @param StatelessApplication $app Stateless Application instance.
     *
     * @throws Throwable if the worker cannot be instantiated from the container.
     */
    public function __construct(private readonly StatelessApplication $app)
    {
        $this->worker = $this->app->container()->get(PSR7WorkerInterface::class);
    }

    /**
     * Executes the RoadRunner request loop for the configured {@see StatelessApplication} instance.
     *
     * Handles incoming PSR-7 requests from the RoadRunner worker, delegates processing to the Stateless Application,
     * and emits PSR-7 responses. Automatically shuts down the worker if the application state is clean after handling
     * a request.
     *
     * Exceptions are caught and reported to the RoadRunner worker for error handling.
     *
     * @return int Exit code indicating successful execution ({@see ExitCode::OK}).
     *
     * Usage example:
     * ```php
     * $exitCode = $runner->run();
     * ```
     */
    public function run(): int
    {
        while (($request = $this->worker->waitRequest()) !== null) {
            try {
                $response = $this->app->handle($request);
                $this->worker->respond($response);

                if ($this->app->clean()) {
                    $this->worker->getWorker()->stop();
                }
            } catch (Throwable $e) {
                $error = sprintf(
                    "['%s'] '%s' in '%s:%d'\nStack trace:\n'%s'",
                    $e::class,
                    $e->getMessage(),
                    $e->getFile(),
                    $e->getLine(),
                    $e->getTraceAsString(),
                );

                $this->worker->getWorker()->error($error);
            }
        }

        return ServerExitCode::OK->value;
    }
}
