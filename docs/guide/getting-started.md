# Getting started with Playwright PHP

Playwright PHP drives real browsers from ordinary PHP. Install the package,
install its browser binaries, then run one small script before adding it to a
test suite.

## Requirements

- PHP 8.2 or later;
- Node.js 20 or later, used by the bundled Playwright server;
- Composer.

Check the tools available to the project:

```bash
php --version
node --version
composer --version
```

You do not write Node.js code. It runs the Playwright server that communicates
with the browser.

## Install the package

From the project root:

```bash
composer require --dev playwright-php/playwright
```

Composer creates `vendor/` and exposes the browser installer at
`vendor/bin/playwright-install`.

## Install browser binaries

```bash
vendor/bin/playwright-install --browsers
```

This installs Playwright's default browser set: Chromium, Firefox, and WebKit.
They are Playwright-managed browser builds, not the Chrome, Edge, Firefox, or
Safari applications already installed on the machine.

The cache can also contain `chromium_headless_shell-*`. It is a companion
Chromium executable used for headless runs, not a fourth browser engine or a
separate browser choice.

On a fresh Linux machine or CI runner, install the operating-system packages
that those browsers need as well:

```bash
vendor/bin/playwright-install --with-deps
```

`--with-deps` implies `--browsers`. The installer accepts no browser-name
arguments. Use its `--help` output as the authoritative list of supported
options for the installed package version.

### Use a project-specific browser cache

Set `PLAYWRIGHT_BROWSERS_PATH` when the cache must live in a known location,
for example in an isolated CI cache:

```bash
PLAYWRIGHT_BROWSERS_PATH="$PWD/var/playwright-browsers" \
  vendor/bin/playwright-install --browsers
```

Use the same variable when running the tests. Otherwise the PHP process and
the installer may look in different browser-cache locations.

## Run a first script

Create `first-script.php` in the project root:

```php
<?php

declare(strict_types=1);

require __DIR__.'/vendor/autoload.php';

use Playwright\Playwright;

use function Playwright\Testing\expect;

$context = Playwright::chromium();
$page = $context->newPage();

$page->goto('https://example.com');

expect($page)->toHaveTitle('Example Domain');
expect($page->getByRole('heading'))->toHaveText('Example Domain');

echo "OK\n";

$context->close();
```

Run it:

```bash
php first-script.php
```

Expected output:

```text
OK
```

The script launches Chromium, opens a page, waits for the expected title and
heading, then closes the browser context. `expect()` retries browser
assertions until their condition is met or the assertion times out.

## Watch the browser while developing

Use a headed browser locally when you need to see the page:

```php
$context = Playwright::chromium(['headless' => false]);
```

Keep normal scripts headless unless visible browser UI is part of the scenario.

## Troubleshooting the first run

- If `vendor/bin/playwright-install` does not exist, run Composer from the
  project root and confirm `playwright-php/playwright` is installed there.
- If Node.js cannot be found, install Node.js 20 or later, then rerun the
  installer.
- If a browser cannot launch on Linux, rerun the installer with `--with-deps`.
- If the first assertion times out, keep `https://example.com` until this
  baseline succeeds. Only then point the script at the application.

## Next steps

- [Core concepts](core-concepts.md): browser, context, page, and locator.
- [Testing with PHPUnit](testing-with-phpunit.md): move the script into a test
  suite.
- [Assertions](assertions-reference.md): choose a browser assertion that
  describes the product result.
