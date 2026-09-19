---
title: How does Double choose which expectation wins?
description: A default and a specific override used to be ordered by which line came first. That changed — and closed off a mistake the old rule made easy to write.
published: 2026-09-21
---

# How does Double choose which expectation wins?

```php
$repository->allows('find')->returns(null);              // a default
$repository->allows('find')->with(123)->returns($book);  // a specific override
```

That used to work only in this order. Matching was most-recently-registered first, so the specific override had to come *after* the default or it never got reached. Now it doesn't matter which line comes first — and along the way, Double closed off a mistake the old rule made easy to write.

## Specific beats recent

`with()` narrows an expectation to a particular call. A bare `allows('find')->returns(null)` matches any call to `find`; adding `with(123)` matches only `find(123)`. Order used to be the only thing deciding which one won, so the two lines above only behaved the way they read if the broad case happened to come first.

Specificity decides first now, order second. An expectation counts as specific if `with()` pins at least one argument down to something narrower than "anything" — a bare `with(Argument::any())` doesn't count, since it matches exactly as much as no `with()` at all. `find(123)` gets `$book` and `find(456)` gets `null`, no matter which line was written first. Two specific expectations aren't ranked against each other for how narrow they are, though — `with(123)` and `with(Argument::type('int'))` are both just "specific," and plain most-recent-first order settles it between them.

This is what finally makes Mockery's `byDefault()` translate directly instead of needing a note about registration order. Register a plain fallback and a `with()`-qualified override, in either order — the specific one wins, and falls back to the generic one once its own `times()` budget runs out.

## The trap the old rule made easy

Some setups look like they should produce a sequence of answers but don't:

```php
$repo->expects('find')->with(123)->returns($first);
$repo->expects('find')->with(123)->returns($second);
```

Same method, identical `with()` — so these two are tied, and matching still falls back to most-recently-registered first between them. The first call to `find(123)` gets `$second`; the second gets `$first`. Backwards from how it reads. It's a natural habit to carry over from Mockery, where registering the same call twice with `once()` is exactly how you'd queue up answers in order.

Double doesn't try to guess this was a mistake and quietly reorder it for you — that's worse than refusing to run. `verify()` recognizes two or more expectations on the same method, with identical argument matching and finite call counts, as ambiguous, and throws with the fix inline:

```
Double `UserRepository` has `find(123)` registered 2 times. This looks like an
attempt to return values in sequence. To be explicit about the order, combine
them into one expectation instead.

For example: `find(...)->times(2)->returns(...)`.
```

The fix is what the message says: one expectation, one `times()`, values in the order you want them back.

```php
$repo->expects('find')->with(123)->times(2)->returns($first, $second);
```

Both changes are covered in [Matching Order](../04-expectations.md#matching-order), and the Mockery version of the trap above — plus the `byDefault()` translation it unblocks — is in [Migrating from Mockery](../09-migrating-from-mockery.md#repeating-a-call-to-get-a-sequence-of-answers).
