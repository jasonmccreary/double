---
title: How Double chooses which expectation wins
description: A detailed review of how Double improves the developer experience by adjusting its logic for choosing which expectation wins.
published: 2026-09-21
---

# How Double chooses which expectation wins

By default, Double reviews expectations in last in, first out (LIFO) order. That means if the last expectation you wrote can match, it wins.

That worked as expected when you wrote your default expectation before your specific expectation.

```php
$repository->allows('find')->returns(null);              // a default
$repository->allows('find')->with(123)->returns($book);  // a specific override
```

But if you wrote your default last, Double would choose it.

```php
$repository->allows('find')->with(123)->returns($book);
$repository->allows('find')->returns(null);

$repository->find(123);    // null
```

That's technically correct based on LIFO. Starting with `0.9.0`, both examples above return `$book`.

## Specific beats recent
Specificity decides first now, order second. An expectation counts as specific if `with()` sets the arguments. There are a few caveats. First, a bare `with()` or `with(Argument::any())` doesn't count as specific. Second, specific expectations aren't ranked against each other. So `with(123)` and `with(Argument::type('int'))` are both just specific. Double falls back to LIFO.

With specificity beating LIFO, you may write your expectations however you like and they will be chosen in a natural way.

```php
$repository->allows('find')->returns(null);
$repository->allows('find')->with(123)->returns($first);
$repository->allows('find')->with(456)->returns($second);

$repository->find(456);    // $second
$repository->find(123);    // $first
$repository->find(789);    // null
```

## One more edge case
While choosing the specific expectation first is a more natural developer experience, there is one small edge case. Consider two specific expectations written out fully:

```php
$repo->expects('find')->with(123)->returns($first);
$repo->expects('find')->with(123)->returns($second);
```

Same method, identical `with()` means same specificity. Double falls back to LIFO to decide. That means the first call to `find(123)` gets `$second`. Technically correct with LIFO, but maybe not what you expect. Or maybe it was.

Double can't apply a reordering rule here. At least not with certainty. But it can point you to a shorter way to write this that removes the ambiguity. `verify()` recognizes two or more expectations on the same method, with identical argument matching and finite call counts, as ambiguous, and throws an exception with the fix inline:

```
Double `UserRepository` has `find(123)` registered 2 times. This looks like an
attempt to return values in sequence. To be explicit about the order, combine
them into one expectation instead.

For example: `find(...)->times(2)->returns(...)`.
```

The fix is in the message: write one expectation, using `times()`, and return the values in the order you want them back.

```php
$repo->expects('find')->with(123)->times(2)->returns($first, $second);
```

These two refinements in the decision tree emphasize Double's focus on the developer experience. Choosing expectations based on specificity first is more natural. Just like CSS rules. And addressing the edge case with a clear error message and a single API align with two of Double's goals. Both changes are covered in [Matching Order](../04-expectations.md#matching-order).
