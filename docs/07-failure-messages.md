# Failure Messages

A failing test is a message to whoever's looking at it next. This library
aims for every one of its messages to name the double, name the call, and
point at what to do about it.

Here's what you'll actually see when things go wrong.

## An Expectation Wasn't Met

The most common cause isn't a call that never happened — it's a call that
happened with a slightly different value than expected. A typo, a stale
variable, a case-sensitivity slip. So `verify()` doesn't just report that
an expectation went unmet — if that method was called with something
else, it shows you exactly what:

```php
$repository->expects('find')->with('baz');

$repository->find('Baz'); // note the capital B

$repository->verify();
```

```
1 expectation was not satisfied on test double "foo":

    find("baz") — expected exactly 1 time(s), called 0 time(s)

    "find" was called with different arguments elsewhere in this test:

        find("Baz")
```

That second block comes straight from the call log — `find()` really was
called, just not with anything that matched. Seeing the actual value next
to the one you configured is usually enough to spot what went wrong.

## A Call Wasn't Configured

In [Strict mode](03-creating-test-doubles.md#strict), a call that doesn't
match a configured expectation fails the moment it happens:

```
Test double `foo` received an unexpected call to `bar(1, 2)`. Strict mode
requires every call to be configured. For example:
`$foo->allows('bar')->returns(...)`.
```

That example is a starting point, not something to paste verbatim — `$foo`
is only a best guess at your variable name (derived from the double's
label), and whether you actually want `allows()` or `expects()` here is
your call to make, not the library's.

If `bar` was already called successfully elsewhere in the test, you'll see
that instead of the guess — a fact pulled straight from the call log, not a
suggestion:

```
Test double `foo` received an unexpected call to `bar(1, 2)`. Strict mode
requires every call to be configured.

The following calls to `bar` were made during this test: `bar(1)`
```

### Method Name Suggestions

A typo'd method name is caught the moment you configure it:

```php
$repository->expects('sav'); // meant "save"
```

```
Can't configure "sav" on a test double of "BookRepository": no such method
there. Did you mean "save"?
```

The suggestion only appears when something is genuinely close. If nothing
is, the message simply doesn't guess.

## A Call Happened Too Many Times

If a call matches an expectation but would push it past its configured
maximum, that call fails immediately, by number:

```
Test double "foo" got call #4 to "bar(1)", but the expectation only
allows 3.
```

## A Call Happened Out of Order

For expectations marked
[`inOrder()`](04-expectations.md#keeping-calls-in-order), a call that
arrives too early fails immediately, naming both methods involved:

```
Test double "Connection" received "open()" out of order: "close()" already
happened, and inOrder() requires this to happen no later than that.
```

## Setup Mistakes

A handful of failures are about the test's setup rather than the double
misbehaving mid-test, and these are caught the moment you make the
mistake:

- Doubling a class that doesn't exist, or that's `final` (see
  [What Can't Be Doubled](03-creating-test-doubles.md#what-cant-be-doubled)).
- A method name that collides with the library's own control verbs (see
  [Reserved Method Names](03-creating-test-doubles.md#reserved-method-names)).
- Setting a double's mode twice.
- Configuring a static method with `expects()`/`allows()`/`received()`
  (see [Static Methods](04-expectations.md#static-methods)).
- Calling `->passthru()` with no argument when there's nothing to
  auto-instantiate.

## Fabricated Doubles

[Loose mode](03-creating-test-doubles.md#loose-the-default) sometimes
hands you back a freshly-generated double rather than a plain value. Any
failure message involving one of those says so plainly:

```
Note: this double was auto-fabricated by Loose mode, not created directly.
```

And if a generated double is asked to fabricate something of its own,
that's where a line is drawn:

```
Test double "Book" only fabricates one call chain deep — configure
"author()" explicitly: $book->allows('author')->returns(...);
```

One generated double for free, and then a clear stop with a fix you may
paste directly, rather than a silently growing chain of generated
objects.
