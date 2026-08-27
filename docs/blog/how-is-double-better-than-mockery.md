---
title: How is Double better than Mockery?
description: A reply to a question from r/PHP on what actually improves once you move from Mockery to Double.
published: 2026-08-19
---

# How is Double better than Mockery?

```php
$repository->expects('find')->with(123)->returns($book);
$repository->received('recordView')->with($book);
```

The syntax above is familiar — that's deliberate. The developer experience is where the difference actually lives.

I respect Mockery. I've used it for ten years. But I've also lived with its "paper cuts" that whole time, and I believe other PHP developers have lived with them too. Double exists because it's time someone improved that experience rather than just documenting around it. Three paper cuts in particular are what it focuses on.

## Error messages

We've all seen Mockery's dense failure messages — a mangled class name (`Mockery_1_BookRepository_BookRepositoryInterface`), grammar issues (`should be called exactly 1 times but called 0 times`), and not much else to go on.

I wrote Double's failure messages for humans — describing the failure in clear terms, often with additional context. Here's a real example:

```
Double `foo` expected `bar('baz')` to be called exactly 1 time, but it was never called.

The following calls to `bar` were made during this test: `bar('Baz')`
```

## API clarity

Mockery's API has real gotchas. How many developers forget to chain `->once()` onto `shouldReceive()`? `shouldNotHaveBeenCalled()` is also misleading — it reads like "assert this was not called," but it only checks whether the mock was invoked as a callable.

Double gives each concept exactly one verb: `expects()`, `allows()`, `received()`. Leveraging common terms found in nearly every mocking library. No filler words. No aliases.

## Easier contribution

I've contributed to Mockery over the years — it's a slow process, both in understanding the library and in PR approval. The hard truth is, it was easier (especially with AI) to build Double than it was to "fix" the above in Mockery. I want Double to be easier to contribute to. Both by humans, and AI.

If you're coming from Mockery and want the method-by-method mapping rather than the reasoning behind it, that's covered separately in [Migrating from Mockery](../09-migrating-from-mockery.md).
