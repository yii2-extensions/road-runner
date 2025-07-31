<?php

declare(strict_types=1);

namespace yii2\extensions\roadrunner\runtime;

use Symfony\Component\Runtime\GenericRuntime;
use Symfony\Component\Runtime\RunnerInterface;
use yii2\extensions\psrbridge\http\StatelessApplication;

final class RoadRunnerRuntime extends GenericRuntime
{
    /**
     * Creates the appropriate runner for the given application.
     *
     * Detects if the application is a Yii2 StatelessApplication and returns a specialized RoadRunner runner.
     * For other application types, falls back to the parent implementation.
     *
     * @param object|null $application Application instance to create a runner for.
     *
     * @return RunnerInterface Runner instance for the application.
     */
    public function getRunner(?object $application): RunnerInterface
    {
        if ($application instanceof StatelessApplication) {
            return new RoadRunner($application);
        }

        return parent::getRunner($application);
    }
}
