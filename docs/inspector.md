# Playwright Inspector

Use the inspector while developing a test: to see the rendered page, inspect a
locator, or pause at one uncertain action. It is a local development tool, not
a CI strategy. In CI, retain traces and screenshots instead.

## Start a headed inspector session

Create the Playwright client with a headed configuration, then enable the
inspector on the browser builder:

```php
<?php

declare(strict_types=1);

require __DIR__.'/vendor/autoload.php';

use Playwright\Configuration\PlaywrightConfig;
use Playwright\PlaywrightFactory;

$config = new PlaywrightConfig(headless: false);
$playwright = PlaywrightFactory::create($config);
$browser = $playwright->chromium()->withInspector()->launch();

$page = $browser->newPage();
$page->goto('https://example.com');

$page->pause();

$browser->close();
$playwright->close();
```

`withInspector()` asks the bundled Playwright server to enable its inspector.
`pause()` stops execution at that point until you resume it.

Run the included example with:

```bash
php docs/examples/inspector_demo.php
```

## Use it to improve a locator

Pause around the action that is unclear, then inspect the rendered page before
changing the test:

```php
$page->goto('https://app.example.test/settings');
$page->getByRole('button', ['name' => 'Danger zone'])->click();

$page->pause();

$page->getByRole('button', ['name' => 'Delete account'])->click();
```

Check whether an overlay covers the target, the control is disabled, a frame
boundary was missed, or the page is not at the expected URL. Then encode the
answer in a semantic locator and a browser assertion. Do not leave a test
dependent on manual inspection.

## Move from inspection to repeatable evidence

Before committing the test, remove the pause and ensure it runs headless. For
failures that occur only in CI, collect evidence that does not require a
desktop:

- enable `PW_TRACE=1` for a diagnostic PHPUnit run;
- retain screenshots for failed tests;
- capture console or request evidence only when it answers the failure.

A headed browser can make a flaky test easier to watch, but it does not make
the test deterministic. Wait for product state rather than for an observer.

## Next steps

- [Assertions](guide/assertions-reference.md): state-based waits.
- [Testing with PHPUnit](guide/testing-with-phpunit.md): failure artifacts.
