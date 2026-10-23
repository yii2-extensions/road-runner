# Worker examples

## Entry script

Use the [basic usage](../README.md#basic-usage) entry point with the
[RoadRunner configuration](../README.md#roadrunner-configuration), after registering the
[HTTP worker and message factories](configuration.md#http-worker-and-message-factories). Set the request's secret
`cookieValidationKey` in `config/web.php` and keep the application's routes and identity configuration.

The runner owns `handle()` -> `respond()` -> `finalize(success)` and finalizes exactly once in `finally`. After-send
runs only after successful emission. Do not call `finalize()` again from application event handlers or mix Yii
`run()`/`Response::send()` into this loop. See
[response lifecycle and recycling](configuration.md#response-lifecycle-and-recycling).

For development, add the [worker debug module](../README.md#development--debugging).

## Memory and recycling

```php
use yii2\extensions\psrbridge\http\Application;
use yii2\extensions\roadrunner\RoadRunner;

$app = new Application($config);
$app->setMemoryLimit(256 * 1024 * 1024);

exit((new RoadRunner($app))->run());
```

`clean()` triggers recycling at 90% of the configured threshold; without `setMemoryLimit()`, the bridge reads PHP's
`memory_limit`. When recycling is needed, the runner declines the next pending dispatch before application handling, so
RoadRunner retries it in a fresh worker. RoadRunner's `max_worker_memory` pool setting is a separate supervisor limit.

## Sessions and persistent components

```php
$config['components']['session']['useCookies'] = false;
$config['components']['session']['useStrictMode'] = true;
$config['useSession'] = false; // optional: open sessions lazily in application code
$config['persistentComponents'] = ['db', 'cache'];
```

Lazy sessions remain isolated per visitor. Keep cookie validation enabled with its secret key, and keep `request`,
`response`, `errorHandler`, `session` and `user` out of `persistentComponents`. See
[cookies, lazy sessions, and persistent components](configuration.md#cookies-lazy-sessions-and-persistent-components).

## Uploads and streaming downloads

Retrieve uploads through `yii2\extensions\psrbridge\http\UploadedFile` as in the
[file upload example](../README.md#file-upload-handling), validate their content and size with the application's upload
model, and save them under server-generated names outside the public directory. Upload state resets before each request.

Keep response streams open until `respond()` finishes. For large seekable downloads, configure `PSR7Worker::$chunkSize`
instead of casting the response body to a string; keep the default buffered output for non-seekable bodies such as
pipes.

[Back to documentation](../README.md#documentation)
