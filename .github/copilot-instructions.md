# Copilot AI Coding Agent Instructions for yii2-extensions/psr-bridge

## Project Overview
- This is a Yii2 extension providing PSR-7/PSR-15 bridge functionality for Yii2 applications.
- The codebase is organized by domain: `src/adapter/`, `src/emitter/`, `src/http/`, and `src/worker/`.
- All code is written for PHP 8.2+ and follows strict type and code style rules (see `copilot/code-style.md`).
- The extension is designed for compatibility with Yii2, PSR-7, and PSR-15 interfaces.

## Key Architectural Patterns
- **Adapter Pattern:** `src/adapter/ServerRequestAdapter.php` bridges PSR-7 requests to Yii2's request model.
- **Immutable Value Objects:** Many classes (e.g., HTTP request/response) are immutable; use fluent methods for configuration.
- **PSR-4 Autoloading:** Namespace and directory structure strictly follow PSR-4.
- **Exception Handling:** All exceptions are custom and documented; see `src/emitter/exception/` and `src/http/exception/`.
- **Testing Mirrors Source:** Tests in `tests/` mirror the `src/` structure and use PHPUnit with full Arrange-Act-Assert.

## Developer Workflows
- **Build/Static Analysis:**
  - Run static analysis: `vendor\bin\phpstan analyse`
  - Run code style: `vendor\bin\ecs check`
  - Run tests: `vendor\bin\phpunit`
- **Test Coverage:** 100% coverage is required for PRs. Add tests in `tests/` with the same namespace as the class under test.
- **Conventional Commits:** All commits use `type(scope): description.` (see `copilot/general-instructions.md`).
- **CI/CD:** All PRs are validated by GitHub Actions for static analysis, style, and tests.

## Project-Specific Conventions
- **Type Declarations:** Use `string|null` instead of `?string` for nullable types.
- **Array Types:** Use `@phpstan-var` for complex array types in PHPDoc.
- **Documentation:** All classes and methods must have English PHPDoc, following `copilot/class-documentation.md`.
- **Test Naming:** Test methods use `test<Action><Subject><Condition>` (see `copilot/unit-test.md`).
- **Data Providers:** Place in `tests/provider/` and suffix with `Provider`.
- **Error Messages:** Use enums for standardized exception messages.

## Integration Points
- **PSR-7/PSR-15:** Integrates with any PSR-7 compatible HTTP stack.
- **Yii2:** Extends and adapts Yii2 core request/response classes.
- **External Tools:** Uses PHPStan, ECS, PHPUnit, and Codecov for quality and coverage.

## Examples
- See `src/http/Request.php` for a typical adapter usage pattern.
- See `tests/http/PSR7RequestTest.php` for test structure and coverage expectations.
- See `copilot/` for all project-specific coding and documentation standards.

## References
- [copilot/general-instructions.md](../copilot/general-instructions.md)
- [copilot/code-style.md](../copilot/code-style.md)
- [copilot/class-documentation.md](../copilot/class-documentation.md)
- [copilot/unit-test.md](../copilot/unit-test.md)
- [copilot/static-analysis.md](../copilot/static-analysis.md)

---

If you are unsure about a pattern or workflow, check the `copilot/` directory or ask for clarification in your PR.
