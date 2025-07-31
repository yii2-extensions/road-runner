<?php

declare(strict_types=1);

namespace yii2\extensions\roadrunner;

use JsonException;
use Psr\Http\Message\{ServerRequestFactoryInterface, StreamFactoryInterface, UploadedFileFactoryInterface};
use Spiral\RoadRunner\Http\PSR7Worker;
use Spiral\RoadRunner\Worker;
use Symfony\Component\Runtime\RunnerInterface;
use Throwable;
use yii\base\InvalidConfigException;
use yii\console\ExitCode;
use yii\di\NotInstantiableException;
use yii2\extensions\psrbridge\http\StatelessApplication;

/**
 * RoadRunner runtime integration for Yii2 applications.
 *
 * Provides a PSR-7/PSR-15 compatible runtime bridge for Yii2 applications using RoadRunner.
 *
 * This class implements the {@see RunnerInterface} to enable execution of Yii2 applications in a RoadRunner worker
 * environment, handling PSR-7 requests and responses via the configured {@see StatelessApplication} instance.
 *
 * Key features.
 * - Automatic worker shutdown on application clean state.
 * - Exception-safe request loop with error reporting to RoadRunner.
 * - PSR-7 request/response handling using Spiral RoadRunner PSR7Worker.
 * - Stateless application execution for high-performance PHP runtimes.
 *
 * @see PSR7Worker for Spiral RoadRunner PSR-7 worker implementation.
 * @see RunnerInterface for Symfony Runtime integration.
 *
 * @copyright Copyright (C) 2025 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
final class RoadRunner implements RunnerInterface
{
    private PSR7Worker $worker;

    /**
     * Creates a new instance of the {@see RoadRunner} class.
     *
     * @param StatelessApplication $app Stateless application instance.
     *
     * @throws InvalidConfigException if the configuration is invalid or incomplete.
     * @throws NotInstantiableException if a class or service can't be instantiated.
     */
    public function __construct(private readonly StatelessApplication $app)
    {
        $container = $app->container();

        $this->worker = new PSR7Worker(
            Worker::create(),
            $container->get(ServerRequestFactoryInterface::class),
            $container->get(StreamFactoryInterface::class),
            $container->get(UploadedFileFactoryInterface::class),
        );
    }

    /**
     * Executes the RoadRunner request loop for the configured {@see StatelessApplication} instance.
     *
     * Handles incoming PSR-7 requests from the RoadRunner worker, delegates processing to the stateless application,
     * and emits PSR-7 responses. Automatically shuts down the worker if the application state is clean after handling
     * a request. Exceptions are caught and reported to the RoadRunner worker for error handling.
     *
     * @throws JsonException if a JSON encoding or decoding error occurs during request processing.
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
                $this->worker->getWorker()->error((string) $e);
            }
        }

        return ExitCode::OK;
    }
}
