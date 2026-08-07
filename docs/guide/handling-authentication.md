# Handling authentication

Authentication is test setup unless authentication itself is the behavior under
test. Test login, logout, password reset, and identity-provider redirects in
dedicated scenarios. For an already-authenticated feature, reuse a saved state
file in a fresh browser context.

## Choose the strategy

Use the UI login flow when the login experience is the contract being tested.
Use saved state when authentication is only a prerequisite for a dashboard,
checkout, administration, or role-specific workflow.

This distinction keeps feature failures meaningful. A billing test should not
fail only because a login provider changed its markup.

## Test login through the UI

Assert a stable authenticated state before treating a login as successful:

```php
use function Playwright\Testing\expect;

$page->goto('https://app.example.test/login');

$page->getByLabel('Email')->fill('editor@example.test');
$page->getByLabel('Password')->fill(getenv('TEST_USER_PASSWORD') ?: 'secret');
$page->getByRole('button', ['name' => 'Sign in'])->click();

$page->waitForURL('**/dashboard', ['timeout' => 15000]);
expect($page->getByRole('heading', ['name' => 'Dashboard']))->toBeVisible();
```

Prefer `getByLabel()` and `getByRole()` over CSS selectors. They describe the
user-facing control and make missing accessible names visible in a failure.

## Save a state file

Log in once in a setup script, then save the context's cookies and local
storage:

```php
<?php

declare(strict_types=1);

require __DIR__.'/vendor/autoload.php';

use Playwright\Playwright;

$statePath = __DIR__.'/.auth/editor.json';
is_dir(dirname($statePath)) || mkdir(dirname($statePath), 0700, true);

$context = Playwright::chromium();
$page = $context->newPage();

$page->goto('https://app.example.test/login');
$page->getByLabel('Email')->fill(getenv('TEST_USER_EMAIL') ?: 'editor@example.test');
$page->getByLabel('Password')->fill(getenv('TEST_USER_PASSWORD') ?: 'secret');
$page->getByRole('button', ['name' => 'Sign in'])->click();
$page->waitForURL('**/dashboard', ['timeout' => 15000]);

$context->saveStorageState($statePath);
$context->close();
```

Commit the script, not the generated state file. It can contain session
cookies and local-storage tokens. Put the state directory in `.gitignore`
unless its contents are deliberately safe, fake fixtures.

## Load state into an isolated context

Reuse the file, not a live browser context. Each scenario still needs its own
context so cookies, local storage, permissions, routes, downloads, and open
pages cannot leak to the next test.

```php
use Playwright\PlaywrightFactory;

$playwright = PlaywrightFactory::create();
$browser = $playwright->chromium()->launch();

$context = $browser->newContext([
    'storageState' => __DIR__.'/.auth/editor.json',
]);
$page = $context->newPage();

$page->goto('https://app.example.test/dashboard');
```

For trait-backed PHPUnit tests, load the state after calling
`setUpPlaywright()`:

```php
protected function setUp(): void
{
    parent::setUp();
    $this->setUpPlaywright();
    $this->context->loadStorageState(__DIR__.'/../.auth/editor.json');
}
```

Use one file per meaningful product role, such as `admin`, `editor`, or
`customer`. A separate role makes authorization failures easier to understand.

## Cookies and expired state

For a local application with a test-only session fixture, adding a cookie to a
context can be simpler than going through the UI:

```php
$context->addCookies([
    [
        'name' => 'session',
        'value' => $testSessionId,
        'domain' => 'app.example.test',
        'path' => '/',
        'httpOnly' => true,
        'secure' => true,
        'sameSite' => 'Lax',
    ],
]);
```

The fixture should create a real server-side session. Do not make browser tests
depend on how the application encodes tokens.

Saved state expires. Cookies can time out, a deployment can invalidate a
session, and an identity provider can rotate tokens. Regenerate state in a
dedicated local command or CI setup job. A feature test should report stale
authentication, not silently log in again.

Never print cookies, bearer tokens, credentials, or state-file contents in CI
logs or artifacts.

## Next steps

- [Testing with PHPUnit](testing-with-phpunit.md): test lifecycle and failure
  artifacts.
- [Core concepts](core-concepts.md): contexts and their isolation boundary.
