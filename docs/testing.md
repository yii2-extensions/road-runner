# Testing

This package provides a consistent set of [Composer](https://getcomposer.org/) scripts for local validation.

Tool references:

- [Composer Require Checker](https://github.com/maglnet/ComposerRequireChecker) for dependency definition checks.
- [Easy Coding Standard (ECS)](https://github.com/easy-coding-standard/easy-coding-standard) for coding standards.
- [Infection](https://infection.github.io/) for mutation testing.
- [PHPStan](https://phpstan.org/) for static analysis.
- [PHPUnit](https://phpunit.de/) for unit tests.
- [Rector](https://github.com/rectorphp/rector) for automated refactoring.

## Automated refactoring (Rector)

Run Rector to apply automated code refactoring.

```bash
composer rector
```

## Coding standards (ECS)

Run Easy Coding Standard (ECS) and apply fixes.

```bash
composer ecs
```

## Dependency definition check

Verify that runtime dependencies are correctly declared in `composer.json`.

```bash
composer check-dependencies
```

## Mutation testing (Infection)

Run mutation testing.

```bash
composer mutation
```

Run mutation testing with static analysis enabled.

```bash
composer mutation-static
```

## Static analysis (PHPStan)

Run static analysis.

```bash
composer static
```

## Unit tests (PHPUnit)

Run the full test suite.

```bash
composer tests
```

## Passing extra arguments

Composer scripts support forwarding additional arguments using `--`.

Run PHPUnit with code coverage report generation.

```bash
composer tests -- --coverage-html code_coverage
```

Run PHPStan with a different memory limit.

```bash
composer static -- --memory-limit=512M
```

## Worker compatibility checks

Run the package suite with each supported Yii line in an isolated checkout, keeping PHP 8.3 or higher:

```bash
composer require yiisoft/yii2:2.0.55 --with-all-dependencies
composer tests
composer require yiisoft/yii2:22.0.x-dev --with-all-dependencies
composer tests
```

These commands change the checkout's Composer requirements and lock file; use disposable checkouts for a matrix.
The suite uses real bridge request/response processing with mocked RoadRunner transport. It covers emission and
processing failures, after-send ordering, finalization when error reporting fails, declined dispatches during
recycling, preservation of finalization and shutdown exceptions, session cookie selection, and upload reset. It does not start the RoadRunner server.

For an application smoke test, run its configured RoadRunner binary and send consecutive requests through the
same worker: two visitors, absent/invalid session cookies, an upload followed by no upload, and streamed downloads.
Verify response bytes and status, after-send ordering, and worker replacement after a fatal callback. Confirm each
request enters application handling once and the first request after replacement succeeds. Record the
binary version and dependency revisions. If the binary or application fixture is unavailable, report that runtime
check as pending rather than treating mocked transport tests as server validation.
