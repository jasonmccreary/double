# Verification

`expects()` and `allows()` describe what you expect to happen before the code under test runs. Verification is the other half: confirming, afterward, that things happened the way you set up.

There are three things you may check, and two ways to check them.

## Checking Your Expectations: `verify()`

```php
$repository->expects('save')->with($book);

$service->create($book);

$repository->verify();
```

`verify()` looks at every `expects()` you configured on the double and fails if any of them weren't met. If you're using PHPUnit, you don't need to call this yourself at all. See [PHPUnit Integration](08-phpunit-integration.md) for a trait that runs it automatically at the end of every test. Otherwise, call it once, wherever your test naturally ends.

When something's unmet, the message doesn't just say it didn't happen. It shows you what did happen instead, if anything, which is usually enough to spot a typo or a stale value at a glance. See [Failure Messages](07-failure-messages.md) for an example.

## Checking What Happened: `received()`

Sometimes you'd rather not declare an expectation up front, and just look back afterward to confirm something happened:

```php
$service->create($book);

$repository->received('save')->with($book);
```

You may compose it the same way you would an expectation:

```php
$repository->received('save')->with($book)->times(2);
$repository->received('delete')->never();
```

Leave every modifier off and `received('save')` already means "was this called at least once." Add `with()` to narrow it to specific arguments, and `never()`/`times()` to be precise about the count.

### When the Check Runs

A `received()` call doesn't check anything the moment you write it, since it doesn't yet know whether you're about to chain `->with()` or `->never()` onto it. The check runs once the chain is finished, which for most test code is the instant the statement completes:

```php
$repository->received('save')->with($book)->never(); // checked right here
```

If you assign a `received()` chain to something that outlives the statement (a property on the test case, say, rather than a local variable), there's no guarantee it's checked right away. Using the [PHPUnit `VerifiesDoubles` trait](08-phpunit-integration.md) removes this concern entirely: every `received()` assertion made during a test is checked once that test ends, regardless of what you did with it.

## Checking Nothing Happened: `unused()`

`received($method)->never()` checks one named method. Sometimes you want something stronger: a double that a code path should never have touched at all, regardless of which of its methods that would have been:

```php
$service->create($book);

$logger->unused();
```

`unused()` fails the moment any method was called on the double, naming every call it actually saw:

```
Double `Logger` expected no calls at all, but received: `info('something happened')`.
```

Unlike `received()`, there's no fluent chain to wait on. `unused()` checks immediately, the same way `verify()` does, rather than deferring until the statement (or the test) finishes.

This is also the fix for a well-known Mockery trap: `shouldNotHaveBeenCalled()` sounds like "this spy received no calls," but it only checks whether the mock itself was invoked as a callable. It says nothing about calls to its methods, which is what most people actually mean. `unused()` checks exactly that: every method, zero calls.
