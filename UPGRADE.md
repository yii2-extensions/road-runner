# Upgrade Guide

## 0.4.0

- Upgrade PHP to 8.3 or higher.
- Upgrade Yii to 2.0.55 or later in the 2.0 line, or use the supported Yii 22 branch.
- If you maintain a custom request loop, call `Application::finalize()` once after each emission attempt, passing
  `false` when processing or emission fails. Let finalization errors propagate without another emission or error
  report. `RoadRunner::run()` now owns this lifecycle; remove duplicate finalization or manual after-send calls
  around it. For a custom loop, request worker shutdown only with a pending dispatch, before calling `handle()`
  for that dispatch; let RoadRunner retry it in another worker.
- Remove request, response, error handler, session, and user instances from `persistentComponents`. With lazy
  sessions, expect a missing, invalid, or tampered cookie to clear the previous request's session ID. Disable native
  session cookies before opening sessions, retain Yii cookie validation, and supply its secret key.

## 0.2.0

### Breaking changes

- `yii2\extensions\psrbridge\http\StatelessApplication` was renamed to `yii2\extensions\psrbridge\http\Application`.
- No compatibility alias is provided for `StatelessApplication`; all imports and type hints must be updated.

### Migration steps

#### 1) Update imports and type hints

Replace all references to the old class in constructors, properties, method signatures, and PHPDoc:

```php
use yii2\extensions\psrbridge\http\Application;
```

```php
private Application $app;
```

```php
/** @phpstan-param Application<IdentityInterface> $app */
```

#### 2) Update instantiation sites

Replace imports and instantiation sites:

```php
use yii2\extensions\psrbridge\http\Application;
```

```php
$app = new Application($config);
```
