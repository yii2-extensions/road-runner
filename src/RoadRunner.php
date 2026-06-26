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
     * Reports failures through `error()` only while the response has not been emitted: once `respond()` succeeds, the
     * worker has already received the response, so {@see Application::finalize()} runs after the `try` block.
     *
     * An after-send failure then propagates to recycle the worker instead of reporting a second outcome through
     * `error()`, which the RoadRunner protocol forbids for an already-responded request.
     *
     * @throws Throwable When an after-send handler fails after the response has been emitted.
     *
     * @return int Exit code indicating successful execution ({@see ServerExitCode::OK}).
     */
    public function run(): int
    {
        $worker = Yii::$container->get(PSR7WorkerInterface::class);

        while (($request = $worker->waitRequest()) !== null) {
            try {
                $response = $this->app->handle($request);

                $worker->respond($response);
            } catch (Throwable $e) {
                $this->app->finalize(false);

                $error = sprintf(
                    "['%s'] '%s' in '%s:%d'\nStack trace:\n'%s'",
                    $e::class,
                    $e->getMessage(),
                    $e->getFile(),
                    $e->getLine(),
                    $e->getTraceAsString(),
                );

                $worker->getWorker()->error($error);

                continue;
            }

            $this->app->finalize();

            if ($this->app->clean()) {
                $worker->getWorker()->stop();
            }
        }

        return ServerExitCode::OK->value;
    }
}
