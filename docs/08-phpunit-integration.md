# PHPUnit Integration

Nothing in this library requires PHPUnit. But if it's installed in your
project, three things improve automatically.

## Failures Read as Failures

PHPUnit only reports a thrown exception as a **failure** — attributed
cleanly to the assertion that didn't hold — if it extends PHPUnit's own
`AssertionFailedError`. Anything else is reported as an **error**
instead. An unmatched call or an unmet expectation is very much the
former, so when PHPUnit is present, this library's exceptions are
automatically the failure-flavored version. There's nothing to
configure — it's detected the moment your tests run, and it works the
same way with both PHPUnit 11 and 12.

Setup mistakes — a `final` class you tried to double, a reserved method
name collision — remain **errors**, which is correct: those tell you the
test can't run as written, not that an assertion about your code's
behavior failed.

## Passing Checks Count as Assertions

PHPUnit flags a test "risky" when it runs to completion without ever
touching its assertion counter — usually a sign the test forgot to check
anything. A test whose only check is `$double->received(...)`,
`$double->unused()`, or a satisfied `expects()`/`allows()` verified via
`verify()` (or the `VerifiesDoubles` trait below) is a real, meaningful
check, but without this integration PHPUnit would have no way to know
that: nothing in this library ever touched PHPUnit's own assertion API.

When PHPUnit is present, a passing verification registers a genuine
PHPUnit assertion behind the scenes, so tests like these are never flagged
as risky. Nothing to configure — this is on by default, and a failing
check throws exactly as before.

## Automatic Verification

You may add one trait to your base test case to stop calling `->verify()`
yourself:

```php
use JMac\Testing\Integrations\PHPUnit\VerifiesDoubles;

class TestCase extends \PHPUnit\Framework\TestCase
{
    use VerifiesDoubles;
}
```

From then on, every double created during a test — and every `received()`
assertion made on one — is checked automatically once that test finishes.

`$double->verify()` still works, and remains the only option for every
other test runner, or for PHPUnit users who'd rather call it explicitly.
Adding the trait doesn't change what `verify()` does; it just gives you a
way to stop calling it yourself.
