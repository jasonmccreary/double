# Expectations

This is the part you'll write the most — telling a double what you expect
to happen, and what should happen when it does.

```php
$repository->expects('find')->with(123)->returns($book);
```

Read left to right, that's the whole sentence: expect a call to `find`,
with `123`, and return `$book`. Every modifier here has a sensible
default if you leave it off.

## `expects()` and `allows()`

Both register a configured call. The difference is what happens if it
never happens:

```php
$repository->expects('save');  // must be called exactly once — verify() fails otherwise
$repository->allows('save');   // may be called any number of times, including zero
```

You may reach for `expects()` when the call is the point of the test —
the thing you're actually asserting happened — and `allows()` for setup
that just needs to respond to a call, without the count being part of
what you're testing.

## Constraining Arguments with `with()`

```php
$repository->allows('find')->with(123)->returns($book);
```

Leave `with()` off and the expectation matches a call to that method with
any arguments. A plain value passed to `with()` is compared directly —
scalars and arrays with `===`, objects with `==` (an equivalent object,
not necessarily the same instance; see
[Argument Matching](05-argument-matching.md#same-exact-instance) if you
need identity instead).

For anything more flexible than an exact value, see
[Argument Matching](05-argument-matching.md).

## Deciding What Happens

```php
$repository->allows('find')->with(123)->returns($book);
$repository->allows('find')->with(999)->throws(new NotFoundException());
$repository->allows('calculateTax')->resolves(fn (...$args) => $realGateway->calculateTax(...$args));
```

- `returns(...)` hands back a value.
- `throws(...)` throws an exception instead.
- `resolves(...)` computes the return value with a closure, given the
  real call's arguments. This is also how you may delegate a single call
  to a real object without switching the whole double to
  [passthru](03-creating-test-doubles.md#passthru).

If you leave all three off, a matched call falls back to the same safe
default that [Loose mode](03-creating-test-doubles.md#loose-the-default)
uses for an unmatched one, so an expectation without an explicit return
doesn't hand back a bare `null` that turns into a `TypeError` further
down.

## Sequential Returns

You may pass more than one value to `returns()` (or `throws()`), and each
call receives the next value in the list, holding at the last one once
the list runs out:

```php
$repository->allows('find')->with(1)->returns($first, $second);

$repository->find(1); // $first
$repository->find(1); // $second
$repository->find(1); // $second again
```

This is one expectation with a queue attached, not several competing
expectations, so it composes cleanly with everything else on this page.

## Counting Calls with `times()`

```php
$repository->expects('save')->times(3);                 // exactly 3
$repository->expects('save')->times(1, 3);               // between 1 and 3
$repository->expects('save')->times(minimum: 2);         // at least 2
$repository->allows('save')->times(maximum: 5);          // at most 5
$repository->expects('save')->atLeastOnce();             // shorthand for times(minimum: 1)
$repository->allows('save')->never();                    // shorthand for times(0)
```

One overloaded verb covers every count you'd want, rather than a separate
word for each shape. `atLeastOnce()` and `never()` remain as their own
methods because they read more naturally than the equivalent `times()`
call for those two common cases.

## Matching Order

When more than one expectation could match a call, write your setup top
to bottom and the last one that applies wins:

```php
$repository->allows('find')->returns(null);              // a default, declared first
$repository->allows('find')->with(123)->returns($book);  // a specific override, declared second
```

This mirrors how you'd naturally write the setup: state a broad default,
then layer specific overrides underneath it.

## Keeping Calls in Order

Most tests don't need to care what order unrelated calls happen in. But
sometimes order is genuinely part of the contract — you can't `commit()`
before `beginTransaction()`. For that, mark the relevant expectations
`inOrder()`:

```php
$connection->expects('open')->inOrder();
$connection->expects('write')->inOrder();
$connection->expects('close')->inOrder();
```

Calling `write()` before `open()` throws immediately, naming both methods
involved. Expectations without `inOrder()` are unaffected, and ordering
is only checked within a single double.

## Static Methods

`expects()`, `allows()`, and `received()` only work with instance
methods — there's no instance for a double to intercept a static call
through. Configuring one is rejected up front, with a clear reason,
rather than silently doing nothing:

```php
$repository->expects('findAll'); // findAll() is `public static function`
// Can't configure "findAll" on a test double of "BookRepository": it's a
// static method, and test doubles only intercept instance calls.
```
