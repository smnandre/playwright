# Testing with PHPUnit

Use the PHPUnit trait when a browser flow belongs in the project's test suite.
It keeps the Playwright lifecycle explicit while giving each test a fresh
browser context and page.

## Requirements

Install PHPUnit and Playwright PHP in the same project, then install the
browser binaries:

```bash
composer require --dev phpunit/phpunit playwright-php/playwright
vendor/bin/playwright-install --browsers
```

The trait supports PHPUnit 10 and later.

## Write a first browser test

Create `tests/Browser/HomepageTest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Browser;

use PHPUnit\Framework\Attributes\Group;
use Playwright\Testing\PlaywrightTestCase;

#[Group('browser')]
final class HomepageTest extends PlaywrightTestCase
{
    public function testExamplePageHasAHeading(): void
    {
        $this->page->goto('https://example.com');

        $this->expect($this->page)->toHaveTitle('Example Domain');
        $this->expect($this->page->getByRole('heading'))
            ->toHaveText('Example Domain');
    }
}
```

Run the test:

```bash
vendor/bin/phpunit tests/Browser/HomepageTest.php
```

PHPUnit should report one passing test.

## What the base class owns

`PlaywrightTestCase` uses `PlaywrightTestCaseTrait` and provides these protected
properties for each test:

- `$this->playwright`: the Playwright client;
- `$this->browser`: the shared browser process when the default configuration
  is used;
- `$this->context`: an isolated browser session;
- `$this->page`: a new tab in that context.

The base class creates a fresh context and page in its `setUp()` method. It
closes the context in `tearDown()`.

The browser process may be shared between tests. Do not share a context: it
contains cookies, local storage, permissions, route handlers, downloads, and
open pages that would leak test state.

If the project must extend a different PHPUnit base class, use
`PlaywrightTestCaseTrait` directly and call `setUpPlaywright()` and
`tearDownPlaywright()` from the inherited PHPUnit hooks.

## Keep the first suite clear

Write one user-visible goal per test. A click is input; the assertion should
describe the result a user receives.

```php
$this->page->getByRole('button', ['name' => 'Save'])->click();

$this->expect($this->page->getByRole('status'))
    ->toHaveText('Saved');
```

Use semantic locators such as `getByRole()` and `getByLabel()` where the
application provides them. They make a failure easier to read and also expose
missing accessible names.

Use the `browser` group to keep fast PHP-only tests separate from browser
tests:

```bash
# Fast lane: no browser.
vendor/bin/phpunit --exclude-group browser

# Browser lane only.
vendor/bin/phpunit --group browser
```

## Capture failures

When a trait-backed test fails or errors, it saves a screenshot under
`test-failures/` in the current working directory. Enable traces for a
diagnostic run:

```bash
PW_TRACE=1 vendor/bin/phpunit --group browser
```

With tracing enabled, failing tests also write a trace archive beside their
screenshot. Open the archive with Playwright Trace Viewer. Do not enable
tracing permanently for every run unless the added artifacts are intentional.

## Troubleshooting

- If PHPUnit cannot find the trait, confirm that the project running PHPUnit
  also requires `playwright-php/playwright`.
- If no test is discovered, check the test path, class name, namespace, and
  PHPUnit configuration.
- If the browser cannot launch, run
  `vendor/bin/playwright-install --browsers`; on a fresh Linux runner, use
  `--with-deps`.
- If an assertion times out, first run the standalone script from
  [Getting started](getting-started.md). It separates browser setup failures
  from PHPUnit integration failures.

## Next steps

- [Assertions](assertions-reference.md): locator and page assertions.
- [Handling authentication](handling-authentication.md): reuse a saved state,
  not a live context.
- [Inspector](../inspector.md): diagnose a failing test locally.
