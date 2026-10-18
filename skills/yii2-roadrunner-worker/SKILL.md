---
name: yii2-roadrunner-worker
description: Integrate yii2-extensions/road-runner with a Yii PSR Bridge application, including worker factories, response finalization, request isolation, and runtime checks.
license: BSD-3-Clause
---

# Integrate the Yii RoadRunner worker

Use `yii2\extensions\roadrunner\RoadRunner` with `yii2\extensions\psrbridge\http\Application`. Check the installed
package revisions and Composer requirements before copying examples. The adapter requires PHP 8.3+, Yii
`~2.0.55 || ^22.0@dev`, and PSR Bridge's `finalize()` lifecycle.

## Read the relevant contract

In a root package checkout, use `composer show --self --format=json` for metadata. For installed dependencies,
resolve the real path and read the configured vendor directory's `composer/installed.php` (`versions[<package>]`):
`install_path`, `pretty_version` and Composer's selected `reference`. Do not blindly prefer `source.reference`
over `dist.reference`. For any live package checkout, including a symlinked path install, inspect its current
`HEAD` and files even when clean; report metadata mismatches and let the actual files govern. Uncommitted changes
have no matching SHA. Otherwise use the selected reference or a verified matching tag only after establishing
its source provenance. Compare `composer.lock`; it may differ from the inspected installation. Identify a
lock-only state when no package files are installed, and resolve dependencies separately. Use hosted pages,
catalogs or snapshots only when their source revision matches.

Resolve the bridge separately. Prefer matching supplied or checkout guides. If an archive excludes `docs/`, read
these files at the resolved reference in <https://github.com/yii2-extensions/road-runner>:

- `docs/installation.md`: package and binary setup.
- `docs/configuration.md`: worker factories, sessions, and response lifecycle.
- `docs/testing.md`: package checks and server smoke tests.
- `UPGRADE.md`: consumer migration actions.

If guides are unavailable or offline, inspect local source/PHPDoc and the changelog, and report the gap. Check the
RoadRunner binary version separately; it is not the Composer adapter version.

Read the bridge's source or corresponding guide for exact request, cookie, upload, and stream behavior. Avoid
substituting another runtime's APIs or loading unrelated guides.

## Configure the application and worker

Supply a PSR-7/PSR-17 implementation. Register response, server-request, stream, and uploaded-file factories in the
bridge application's container configuration. Register `Spiral\RoadRunner\Http\PSR7WorkerInterface` with
`PSR7Worker`, passing `Worker::create()` and the server-request, stream, and uploaded-file factories in that order.
The runner resolves the worker when `run()` starts; the bridge configures the container during construction.

Reuse the application across requests and let the bridge recreate request-sensitive components. Keep request,
response, error handler, session, and user instances out of `persistentComponents`. Preserve cookie validation,
configure its secret key, and disable native PHP session cookie output before sessions open. `useSession=false`
allows lazy opening; the bridge still selects or clears the current visitor's session ID on each request. Use
bridge `http\UploadedFile` for model uploads and validate files before saving to server-controlled paths.

## Preserve delivery and recycling

`RoadRunner::run()` owns `handle(request)` -> `respond(response)` -> `finalize(success)`. Do not wrap it in another
finalizer or invoke Yii response `send()`. Finalization runs once in `finally`, with success set only after
`respond()` returns. A processing or emission error is reported through the worker; finalization errors propagate
after the shutdown handshake without another response or error report. A shutdown failure remains available as
the previous exception. Memory is checked after success and reported failures.

Keep streams alive until emission finishes; use the PSR worker's `chunkSize` when streaming large bodies. A
`clean()` result of `true` means memory reached its threshold. Mark the worker for recycling, then decline the next
pending dispatch with `stop()` before application handling so RoadRunner can retry it in another worker. Continue
receiving until RoadRunner sends its stop command. Keep worker stdout free of application output that could corrupt the transport protocol.

## Verify the result

Run applicable package and application tests. Exercise consecutive visitors, missing/tampered/malformed session
cookies, an upload followed by no upload, emission success/failure, and after-send failure. Confirm finalization
runs once and after-send follows successful emission only. Verify stream bytes and worker recycling when relevant.
A mocked worker does not prove a running server integration: report the missing binary or fixture if a RoadRunner
smoke test cannot run. Skill installation, client discovery, activation, and functional results are separate checks.
