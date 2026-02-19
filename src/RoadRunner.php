<?php

declare(strict_types=1);

namespace yii2\extensions\roadrunner;

use Spiral\RoadRunner\Http\PSR7WorkerInterface;
use Throwable;
use yii\web\IdentityInterface;
use yii2\extensions\psrbridge\http\{Application, ServerExitCode};

use function sprintf;

/**
 * Runs the RoadRunner request loop for a Yii PSR bridge application.
 *
 * Usage example:
 * ```php
 * $config = require dirname(__DIR__) . '/config/web.php';
 *
 * $app = new \yii2\extensions\psrbridge\http\Application($config);
 * $runner = new \yii2\extensions\roadrunner\RoadRunner($app);
 * $exitCode = $runner->run();
 * ```
 *
 * @see Application for the PSR bridge application implementation.
 *
 * @copyright Copyright (C) 2025 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
final class RoadRunner
{
    /**
     * RoadRunner PSR-7 worker instance for handling requests.
     */
    private readonly PSR7WorkerInterface $worker;

    /**
     * Creates a new instance of the {@see RoadRunner} class.
     *
     * @param Application $app PSR bridge application instance.
     *
     * @throws Throwable if the worker cannot be instantiated from the container.
     *
     * @phpstan-param Application<IdentityInterface> $app
     */
    public function __construct(private readonly Application $app)
    {
        $this->worker = $this->app->container()->get(PSR7WorkerInterface::class);
    }

    /**
     * Processes requests from the configured {@see Application} instance until the worker returns `null`.
     *
     * @return int Exit code indicating successful execution ({@see ServerExitCode::OK}).
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
