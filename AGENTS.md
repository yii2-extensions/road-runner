# RoadRunner package guidance

- Work within this package and preserve unrelated changes and staging.
- Use PHP 8.3+ and verify both supported Yii lines: `~2.0.55 || ^22.0@dev`.
- Read `composer.json`, `src/RoadRunner.php`, and the installed PSR Bridge lifecycle before changing integration code.
- Keep `handle()` -> RoadRunner emission -> exactly one `finalize(success)` per attempt. Finalization errors must
  propagate after the shutdown handshake without a second response or worker error report. Preserve shutdown
  errors as the previous exception. Check memory after success and reported failures; decline the next pending
  dispatch before application handling when recycling.
- Preserve request isolation in the bridge; never retain request, response, error handler, session, or user instances.
- Run the package tests and applicable static/style checks. Distinguish mocked transport coverage from a real
  RoadRunner smoke test; report unavailable runtime checks.
- Keep English PHPDoc concise and refer to existing examples instead of duplicating them in class headers.
- Preserve README branding, badges, examples, and useful links. Keep installation concise and UPGRADE limited to
  incompatibilities and user actions. Append CHANGELOG entries to the relevant section.
- Maintain the public skill in `skills/yii2-roadrunner-worker/`; client directories contain links to that source.
- Keep evaluation fixtures, reports, and maintainer tools under ignored, archive-excluded `internal/`. Resources
  required by a public skill must ship with it and must not depend on `internal/`.
- Do not stage, commit, push, publish, or install system dependencies without the user's authorization.
