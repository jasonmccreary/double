---
title: What does a good failure message look like
description: A close read of a real Double failure message, piece by piece.
published: 2026-08-24
---

# What does a good failure message look like

Most test doubles fail the same way: they tell you *that* something went wrong and leave you to figure out *what*. You get a boolean, a stack trace pointing at the library's internals instead of your test, or a dump of an object graph you now have to diff by eye. [Double](https://github.com/jasonmccreary/double) treats that moment differently — the failure message is the product, not an afterthought.

Here's a real one, produced by a genuinely common mistake — an expectation that's close, but not quite right:

```
Double `UserRepository` expected `update(42, 'jane@example.com', 'admin', true)` to be called exactly 1 time, but it was never called.

The following similar call was made to `update`:
  userId: 42
  email: 'jane@example.com'
  role:
    - 'admin'
    + 'editor'
  active:
    - true
    + false
```

Nothing here is generic. Every line answers a question you'd otherwise have to go dig for: which double, which method, what you expected, and what actually happened instead. Four distinct pieces of information are doing that work, and each one deserves a closer look.

## The underlying object

```
Double `UserRepository` expected ...
```

The message opens by naming `UserRepository`, not `Double` or some generic label. That's the real class or interface being doubled, pulled by reflection off the type you passed to `Double::for()` — not a variable name or a string you supplied yourself.

That distinction matters as soon as a test double touches more than one collaborator. `$repository` tells you which PHP variable failed; `UserRepository` tells you which *dependency* failed, and that's the fact you actually need when a test file has three or four doubles in play and only one of their expectations broke. It's also the fact that survives refactors — rename the local variable and the message doesn't change, because it was never derived from the variable in the first place.

## The method expectation

```
... expected `update(42, 'jane@example.com', 'admin', true)` ...
```

This is the call as you configured it — not a paraphrase, the literal signature. Every argument is rendered in the same form you'd write it in PHP: `42` as an int, `'jane@example.com'` as a quoted string, `true` as a boolean. There's no `Object (0x1a2b3c4d)` or `Array (...)` placeholder standing in for a value the message decided not to show you.

That fidelity is what makes the rest of the message legible. Once you can see the exact call you expected, you have a fixed point to compare everything else against — including the one place where the message inevitably has to draw a line: what happens after you scroll past this point in the article, which is where the failure itself is described.

## The problem

```
... to be called exactly 1 time, but it was never called.
```

This is the part that names the actual failure, not just its symptom. "The test failed" is a symptom. "This expected exactly one call and got zero" is the problem — specific enough to act on without opening a debugger.

That specificity comes from what `expects()` records rather than what it discovers after the fact. When you write `->expects('update')->with(...)`, Double knows the count you asked for; when the call log comes up empty, it can say `never called` instead of a vaguer `not called as expected`. The same slot in the message reads differently for a different kind of mismatch — `called 4 times, but your expectation only allowed 3` for a call that happened too often, for instance — because the message is built from what specifically went wrong, not from a single templated sentence with the numbers swapped in. The [Failure Messages](../07-failure-messages.md) chapter walks through several of those variants.

## The similar call

```
The following similar call was made to `update`:
  userId: 42
  email: 'jane@example.com'
  role:
    - 'admin'
    + 'editor'
  active:
    - true
    + false
```

This is the section that turns "it was never called" from a dead end into a lead. A call to `update` *did* happen during the test — just not with the arguments you expected. Rather than leaving you to line up two argument lists by eye, Double pairs them up field by field and shows you only what changed: `userId` and `email` matched, so they're printed plainly for context; `role` and `active` didn't, so each gets a diff.

Two things keep this section honest rather than merely helpful-looking. First, the field names — `userId`, `email`, `role`, `active` — aren't invented; they're the real parameter names from `UserRepository::update()`, reflected straight off the interface being doubled. Second, this block only appears at all when there's exactly one candidate call to compare against. With two or more calls to `update` on record, there's no fact-based way to say which one this expectation was "supposed" to match — so the message doesn't guess, and lists the configured expectations instead. Every comparison you see here is a checked fact, not the library's best guess at what you meant.

## Reading the whole thing

Put back together, the message answers four questions in the order you'd actually ask them: which double, what did I expect, why did it fail, and what actually happened. That order isn't an accident — it's the same order this article walked through, because it's the order a developer reads in when a test goes red.

This is one message, for one kind of failure. Double produces a distinct one for a call made out of order, a mismatched call under `expects()`, a double returned automatically that you didn't configure, and several others — each built from the same principle of naming facts instead of describing symptoms. The [Failure Messages](../07-failure-messages.md) chapter is the full reference.
