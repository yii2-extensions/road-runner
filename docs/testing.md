# Testing

This package provides a consistent set of [Composer](https://getcomposer.org/) scripts for local validation.

Tool references:

- [Actionlint](https://github.com/rhysd/actionlint) for GitHub Actions workflow linting.
- [Codespell](https://github.com/codespell-project/codespell) for spelling checks.
- [Composer Require Checker](https://github.com/maglnet/ComposerRequireChecker) for dependency definition checks.
- [Easy Coding Standard (ECS)](https://github.com/easy-coding-standard/easy-coding-standard) for coding standards.
- [EditorConfig Checker](https://github.com/editorconfig-checker/editorconfig-checker) for EditorConfig compliance.
- [Gitleaks](https://github.com/gitleaks/gitleaks) for secret scanning.
- [Infection](https://infection.github.io/) for mutation testing.
- [Markdownlint-cli2](https://github.com/DavidAnson/markdownlint-cli2) for Markdown linting.
- [PHPStan](https://phpstan.org/) for static analysis.
- [PHPUnit](https://phpunit.de/) for unit tests.
- [Prettier](https://prettier.io/) for Markdown and YAML formatting checks.
- [Rector](https://github.com/rectorphp/rector) for automated refactoring.
- [Yamllint](https://github.com/adrienverge/yamllint) for YAML linting.
- [Zizmor](https://github.com/zizmorcore/zizmor) for GitHub Actions security audits.

## Automated refactoring (Rector)

Run Rector to apply automated code refactoring. This command modifies files.

```bash
composer rector
```

Preview the refactoring without modifying files.

```bash
composer rector-check
```

## Coding standards (ECS)

Check coding standards without modifying files.

```bash
composer ecs-check
```

Apply coding standard fixes. Review the changes before keeping them.

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

Run mutation testing with PHPStan static analysis enabled.

```bash
composer mutation-static
```

Thresholds, thread count and memory options live in `infection.json5`. CI enforces the same thresholds.

## Repository quality checks

Make the CLI tools used by the shared quality workflow available on `PATH`:
actionlint with its ShellCheck helper, codespell, EditorConfig Checker, markdownlint-cli2, Prettier and
yamllint. Run all checks:

```bash
composer quality
```

Each tool also has its own script:

```bash
composer actionlint
composer codespell
composer editorconfig-checker
composer markdownlint
composer prettier
composer yamllint
```

The linter configuration lives in `.github/linters`, and the Prettier configuration in `.prettierrc.json` and
`.prettierignore`. CI uses the same files.

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

## Workflow security checks

Make zizmor and Gitleaks available on `PATH`. Audit the current workflow files
with the same pedantic persona used by the shared security workflow:

```bash
composer zizmor
```

Scan Git history for secrets with the repository's Gitleaks configuration:

```bash
composer gitleaks
```

Run both checks. Findings make the command fail; inspect them rather than
disabling the checks.

```bash
composer security
```

Zizmor needs GitHub API access for its online audits. If the tool runs offline,
report that limit separately. To request an offline audit explicitly:

```bash
composer zizmor -- --offline
```

Report hosted CI execution and its PHP/OS matrix separately from these local
commands. Keep generated reports in ignored `internal/`.

## Passing extra arguments

Composer scripts support forwarding additional arguments using `--`.

Run PHPUnit with code coverage report generation.

```bash
composer tests -- --coverage-html internal/coverage
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

[Back to documentation](../README.md#documentation)
