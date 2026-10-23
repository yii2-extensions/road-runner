# Upgrade Guide

## 0.4.0

- Upgrade PHP to 8.3 or later and Yii to `~2.0.55 || ^22.0@dev`.
- `RoadRunner::run()` now finalizes each request; remove duplicate finalization or manual after-send calls around it. In
  a custom request loop, call `Application::finalize()` once after each emission attempt, passing `false` on failure,
  and let its errors propagate. Request worker shutdown only before calling `handle()` for a pending dispatch.
- Remove request, response, error handler, session and user instances from `persistentComponents`.
- With lazy sessions, a missing, invalid or tampered cookie clears the previous request's session ID. Disable native
  session cookies and keep Yii cookie validation with a secret key.

## 0.2.0

- Rename `yii2\extensions\psrbridge\http\StatelessApplication` to `yii2\extensions\psrbridge\http\Application` in
  imports, type hints, PHPDoc and instantiations; no alias is provided.
