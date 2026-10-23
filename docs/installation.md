# Installation guide

## Requirements

- PHP 8.3 or higher with `sockets`, `filter`, and `mbstring` enabled.
- Composer.
- RoadRunner `^2025.1.8` with HTTP support.
- Yii `~2.0.55 || ^22.0@dev`. Check the selected framework's PHP requirements too.
- A PSR-7/PSR-17 implementation, such as HttpSoft, Nyholm, Guzzle PSR-7, or Laminas Diactoros.

## Install

The examples use HttpSoft. Reuse an existing compatible message implementation if the application already has one.

```bash
composer require yii2-extensions/road-runner:^0.4 httpsoft/http-message
```

Keep Composer's advisory blocking enabled. Review the resolved dependencies, retain `composer.lock`, and run
`composer audit`. Do not disable advisory checks to force an older Yii installation.

Register the [HTTP worker and message factories](configuration.md#http-worker-and-message-factories), configure cookies
and sessions, and create the [entry point and `.rr.yaml`](../README.md#basic-usage).

## Start RoadRunner

From the application root, obtain a binary compatible with the installed RoadRunner constraint and start it:

```bash
vendor/bin/rr get
./rr serve
```

Use the binary's version output to verify the selected version. The Composer packages alone do not start the server.
Keep PHP output on the worker's protocol channel free of `echo`, debug dumps, and accidental output.

[Back to documentation](../README.md#documentation)
