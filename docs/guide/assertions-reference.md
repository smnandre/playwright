# Assertions

An assertion defines the outcome a test protects. Browser assertions should
describe the result a user receives, not only the click or request that caused
it.

Playwright PHP has two browser assertion entry points:

- `Playwright\Testing\expect()` for a `Page` or `Locator` outside PHPUnit;
- `$this->expect()` in a `PlaywrightTestCase` or
  `PlaywrightTestCaseTrait` test.

Both retry browser conditions. The default timeout is five seconds. Use PHPUnit
assertions for values already read into PHP, and `Playwright\Assertions\Expect`
for API responses.

## Assert the product guarantee

After an action, assert the result that matters to the user:

```php
use function Playwright\Testing\expect;

$page->getByRole('button', ['name' => 'Save'])->click();

expect($page->getByRole('status'))->toHaveText('Saved');
```

This is stronger than asserting that the button was clicked. A click is input;
the status message is the product result.

Prefer meaningful locators such as a heading, status region, row, or named
button. If a control has no usable accessible name, fix the UI or add a
deliberate test identifier rather than selecting incidental markup.

## Assert page state

Pass a `Page` when the contract is the route or document title:

```php
expect($page)->toHaveURL('https://app.example.test/dashboard');
expect($page)->toHaveTitle('Dashboard');
```

A URL alone rarely proves that a content-heavy page is ready. Pair it with a
visible result:

```php
expect($page)->toHaveURL('https://app.example.test/dashboard');
expect($page->getByRole('heading', ['name' => 'Dashboard']))->toBeVisible();
```

## Assert locator state

Pass a `Locator` for visible UI state:

```php
expect($page->getByRole('heading', ['name' => 'Dashboard']))->toBeVisible();
expect($page->getByRole('row'))->toHaveCount(3);
expect($page->getByLabel('Email'))->toHaveValue('ada@example.test');
```

Use `toHaveText()` when the expected text may be part of a larger element.
Use `toHaveExactText()` only when the exact text is the product contract.

The available locator checks include visibility, enabled/disabled state,
checked state, focus, text, value, attributes, CSS, and count. The PHP API
reference is the authoritative method list for the installed version.

## Use PHPUnit for PHP values

Browser assertions help while the page is changing. Once a value has been read
into PHP, use normal PHPUnit assertions:

```php
$email = $page->getByLabel('Email');
$email->fill('ada@example.test');

self::assertSame('ada@example.test', $email->inputValue());
```

This boundary is about timing. `inputValue()` reads now. `expect($locator)`
waits until a browser condition is true or the timeout expires.

## Assert direct API responses

When the subject is an `APIResponse`, use the API assertion entry point:

```php
use Playwright\Assertions\Expect;

$response = $context->request()->post('https://app.example.test/api/test/orders', [
    'data' => ['product' => 'keyboard'],
]);

Expect::response($response)->toHaveStatus(201);
Expect::response($response)->toBeOK();
```

API assertions are useful for setup and backend checks. If a feature has a UI,
also assert the visible browser result.

## Keep timeout exceptions local

Document a slower product condition next to its assertion:

```php
expect($page->getByText('Report ready'))
    ->withTimeout(15000)
    ->toBeVisible();
```

Do not raise a global timeout because one report takes longer. Avoid fixed
`sleep()` calls: they hide the condition the test is really waiting for.

## Negative assertions need a positive result

An absence alone can be transient. Pair it with the state that replaces it:

```php
expect($page->getByText('Loading'))->not()->toBeVisible();
expect($page->getByRole('heading', ['name' => 'Results']))->toBeVisible();
```

Use negative assertions for actual absence contracts, such as a dismissed
dialog, removed row, or error message that must not be shown.

## Common mistakes

- Reading `textContent()` immediately after an action when a retrying locator
  assertion expresses the intended wait.
- Asserting generated IDs, CSS classes, or nested markup that users do not
  observe.
- Checking only a URL when the expected content can still be absent.
- Raising a suite-wide timeout instead of explaining one exceptional wait.
- Treating a negative assertion as proof of the final state.

## Next steps

- [Testing with PHPUnit](testing-with-phpunit.md): browser-test lifecycle.
- [Handling authentication](handling-authentication.md): isolated contexts and
  saved state.
