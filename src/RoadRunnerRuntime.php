<?php

declare(strict_types=1);

namespace yii2\extensions\roadrunner;

use Symfony\Component\Runtime\{GenericRuntime, RunnerInterface};
use yii2\extensions\psrbridge\http\StatelessApplication;

/**
 * RoadRunner runtime integration for stateless Yii2 applications.
 *
 * Provides a specialized runtime for seamless interoperability between Yii2 and RoadRunner, enabling stateless
 * application execution compatible with PSR-7/PSR-15 stacks.
 *
 * Extends {@see GenericRuntime} to support automatic runner selection for {@see StatelessApplication} instances,
 * delegating to the parent implementation for other application types.
 *
 * This class ensures that stateless Yii2 applications can be executed efficiently in RoadRunner environments,
 * maintaining compatibility with modern PHP runtimes and middleware architectures.
 *
 * @copyright Copyright (C) 2025 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
final class RoadRunnerRuntime extends GenericRuntime
{
    /**
     * Retrieves the appropriate runner instance for the given application.
     *
     * Returns a {@see RoadRunner} instance if the provided application is a {@see StatelessApplication}, otherwise
     * delegates to the parent implementation.
     *
     * This method enables seamless integration with RoadRunner runtime for stateless Yii2 applications, ensuring
     * compatibility with PSR-7/PSR-15 stacks.
     *
     * @param object|null $application Application instance to run.
     *
     * @return RunnerInterface Runner instance for the provided application.
     *
     * Usage example:
     * ```php
     * $runner = $runtime->getRunner($application);
     * ```
     */
    public function getRunner(object|null $application): RunnerInterface
    {
        if ($application instanceof StatelessApplication) {
            return new RoadRunner($application);
        }

        return parent::getRunner($application);
    }
}
