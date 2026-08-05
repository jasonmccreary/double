# Introduction

[Double](https://github.com/jasonmccreary/double) is a modern PHP double library that puts developer experience first. It was built, from the ground up, on four goals:

- **Zero learning curve.** Create a `Double` and start testing immediately. There's no upfront decision between a mock, a spy, or a partial.
- **Failures for humans.** When an expectation isn't met, the message names the double, the call, and suggests a fix. No terse technical output to decode.
- **One clean API.** Double isn't fancy, just a handful of methods you already know. No aliases. No nuances.
- **Ready for contribution.** Small, well-bounded internals mean no reverse-engineering the whole library for your first PR.

---

Before digging into each goal, we believe code speaks louder than words. So let's start with an example of using Double:

```php
use JMac\Testing\Double;

$repository = Double::for(BookRepository::class);
$repository->expects('find')->with(123)->returns($book);

$service = new CatalogService($repository);
$service->lookup(123);

$repository->received('recordView')->with($book);
```

Double has been designed to be read by a human. This code reads like a sentence: it creates a _double for_ `BookRepository`. That double _expects_ `find` to be called _with_ `123`, and _returns_ `$book`. The double also _received_ a call to `recordView` _with_ `$book`.

## Zero learning curve

Testing already has a barrier to entry. Double does not want to add to that. So there's nothing to decide or learn before getting started. `Double::for()` creates a **loose** double by default — which returns a sensible, type-safe value.

As you set expectations for your double, it automatically adjusts its behavior. You may, of course, set [strict](03-creating-doubles.md#strict) or [passthru](03-creating-doubles.md#passthru) mode if needed. But Double does not require deciding between a mock, a spy, or a partial ahead of time.

## Failures for humans

Double ensures its failures are immediately actionable. First, by actually failing the test. Under PHPUnit, an unmet expectation doesn't just throw — it fails the test the same way a built-in assertion would.

Second, by ensuring the failure messages are as helpful as possible. Each one names the double, the expected call, and why it failed. Most even suggest what to do next. Here's an example:

```
Double `foo` expected `find('baz')` to be called exactly 1 time, but it was never called.

The following calls to `find` were made during this test: `find('Baz')`
```

## One clean API

The grammar is already familiar. Not just if you've used Mockery or PHPUnit, but other testing libraries outside PHP such as RSpec, testdouble.js, or Mockito. There's nothing reinvented or fancy in Double.

In fact, we've made an effort to streamline the grammar. As you'll see, nearly every method is a single word. No fillers. No aliases. While aliases provide flexibility, they also require learning the nuance of each. Consider `shouldReceive()` versus `expects()`. Double keeps one verb per concept: `allows()` and `expects()`.

```php
$repository->expects('find')->with(123)->returns($book);   // called exactly once
$repository->allows('save')->returns(true);                // called any number of times

$repository->allows('find')->with(999)->throws(new NotFoundException());

$repository->save($book);
$repository->received('save')->with($book);                // check it after the fact
```

## Ready for contribution

The codebase is split into a handful of small modules, each with one responsibility. If you want to add an [argument matcher](05-argument-matching.md), start in `src/Matching/`. If you want to tweak an exception message, start in the relevant `*Fields` trait in `src/Exceptions/`. You don't need to untangle the entire codebase to make your contribution.

## Where to Next

- [Installation](02-installation.md) — get it into your project.
- [Creating Doubles](03-creating-doubles.md) — make your first double and choose how it behaves.
- [Expectations](04-expectations.md) — the verbs you'll reach for in nearly every test.
