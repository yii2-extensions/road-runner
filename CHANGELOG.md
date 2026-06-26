# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Conventional Commits](https://www.conventionalcommits.org/en/v1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 0.4.0 Under development

- docs(changelog): convert changelog entries to Conventional Commits format.
- fix: complete the Yii response lifecycle after RoadRunner emits PSR responses and update `yii2-extensions/psr-bridge` to `0.4`.
- chore: align scaffold metadata, GitHub Actions quality and security workflows, and CodeRabbit review configuration with the current repository pattern.
- chore: add social media links to `README.md`.

## 0.3.0 February 28, 2026

- fix(roadrunner): move worker resolution to `RoadRunner::run()` and remove application container bootstrapping from the runner loop to keep application initialization inside `Application`.

## 0.2.0 February 20, 2026

- fix(roadrunner): rename PSR bridge references `yii2-extensions/psr-bridge` from `StatelessApplication` to `Application` across code and documentation.
- fix(composer): update `yii2-extensions/psr-bridge` to `0.2.0` in `composer.json`.

## 0.1.2 January 27, 2026

- fix(docs): update examples in `testing.md` for running Composer scripts with arguments.
- fix(docs): correct command syntax for running PHPStan in `testing.md`.
- fix(docs): update command syntax in `testing.md` to remove redundant `run` prefix for Composer scripts.
- fix(docs): update command syntax in `development.md` and `testing.md` for clarity and consistency.
- fix(docs): update Rector command in `composer.json` to remove unnecessary `src` argument.
- fix(linters): remove redundant ignore rule in `actionlint.yml` configuration.

## 0.1.1 January 25, 2026

- fix(docs): update workflows and documentation for improved CI/CD processes and feature clarity.
- fix(tests): update `tests/support/bootstrap.php` file path and add `TestCase` class for improved testing structure.
- fix(metadata): update `.editorconfig` and `.gitignore` for improved consistency and clarity.
- build(deps-dev): update `symplify/easy-coding-standard` requirement from `^12.5` to `^13.0`.
- docs(readme): update license badge style in `README.md`.
- fix(svg): adjust viewBox dimensions for mobile and desktop SVGs for improved layout.
- feat(dev): add `php-forge/coding-standard` to development dependencies for code quality checks and add support for `PHP 8.5`.

## 0.1.0 October 8, 2025

- feat: introduce `RoadRunner` implementation with tests.
- docs: update `README.md` and documentation for `RoadRunner` integration, including installation, configuration, and usage details; remove `examples.md`.
- refactor: improve dependency management and error handling in `composer.json` and `RoadRunner`.
- fix(tests): correct the type hint for the `statelessApplication` method parameter in `TestCase`.
- fix: correct casing in directory names and update `PHPStan` type hints for configuration files.
- fix(tests): update `TestCase` configuration for request handling and remove unused parameters.
- build(deps): update `spiral/roadrunner` requirement from `^2024.3.0` to `^2025.1.2`.
- docs: update project structure and documentation for clearer application setup.
- docs(readme): update RoadRunner configuration in `README.md` for improved server setup and performance tuning.
- docs(installation): update `RoadRunner` version to `2025.1.2` and adjust project structure example.
- fix(readme): correct development environment settings for `YII_DEBUG` and `YII_ENV`.
- fix(readme): correct configuration file path for `RoadRunner` setup.
- docs: update installation instructions for `RoadRunner` and add `PSR-7` and `PSR-17` message factories.
- fix(composer): correct branch alias for development version to `0.1.x-dev`.
- docs: add development and debugging instructions for `RoadRunner` integration.
- ci: update workflow actions to use `v1` stable version instead of `main` and update `LICENSE.md`.
- docs: add server start instructions and enhance file upload handling in `README.md`.
- docs: clarify application availability in `README.md` after server start instructions.
- docs: update badge styles and reorganize sections in `README.md`.
- docs: add development status badge and update environment variables in `README.md`.
- docs: add mutation testing badge to `README.md`.
- docs: add demo section to `README.md` with a link to the live application template.
- build(deps): bump `php-forge/actions` from `1` to `2`.
