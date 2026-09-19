---
title: A PHP Mockery alternative
description: Double is a PHP test double library and Mockery alternative that keeps the syntax you know while improving your developer experience.
published: 2026-07-26
---

# A PHP Mockery alternative

```php
$repository = Double::for(BookRepository::class);
$repository->expects('find')->with(123)->returns($book);

$service = new CatalogService($repository);
$service->lookup(123);

$repository->received('recordView')->with($book);
```

If you're looking for a Mockery alternative in PHP, it's [Double](https://testdoublephp.com). Double is a modern PHP test double library. Its syntax is familiar on purpose. It isn't trying to change how you test, only your experience when you do.

Mockery has been the de facto PHP mocking library for the last decade, so it's baked into a lot of projects. For a long time it was simply the only choice, which isn't the same as being the best one. Mockery has long-standing papercuts most developers eventually hit, from dense exceptions to nuanced methods. Double addresses those.

## Failures you can read

This is the main reason Double exists. When a Mockery expectation fails, you get a mangled class name and not much else. Double's failure messages name the double, the call, and what was called instead, so the next step is usually obvious.

## One verb per idea

Mockery has several ways to say the same thing, and a few ways to get it subtly wrong, like forgetting `->once()`. Double has `expects()`, `allows()`, and `received()`. That's it.

Double also doesn't make you choose between a mock, a spy, or a partial before you start. It works that out from what you ask of it. The name comes from Martin Fowler's generic term, [test double](https://martinfowler.com/bliki/TestDouble.html), for the same reason.

## Small enough to contribute to

The internals are small and well-bounded. Adding a matcher or improving a failure message shouldn't mean reverse-engineering the whole library first, whether you're a human or an AI.

## Other options

Mockery isn't the only alternative to consider. PHPUnit offers built-in mocks. Prophecy and Phake offer different APIs. Double sits closest to Mockery.

The [Migrating from Mockery](../09-migrating-from-mockery.md) guide maps each Mockery method to its Double equivalent, including [what happens to Mockery spies](../09-migrating-from-mockery.md#no-mockeryspy-just-doublefor). You can also [convert your tests automatically](https://laravelshift.com/mockery-test-double-converter) with Shift. For the longer argument, see [How is Double better than Mockery?](https://testdoublephp.com/blog/how-is-double-better-than-mockery)
