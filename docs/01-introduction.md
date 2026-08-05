# Introduction

[Test Double](https://github.com/jasonmccreary/test-double) is a modern PHP test double library that puts developer experience first. It was built, from the ground up, on four goals:

- **Zero learning curve.** Create a `TestDouble` and start testing immediately. There's no upfront decision between a mock, a spy, or a partial. Out-of-the-box a double behaves _loosely_ and automatically adjusts as you add [expectations](/expectations).
- **Failures for humans.** When an expectation isn't met, the message names the double, names the call, and points at the fix — not just that something, somewhere, didn't match. No terse output, no internal class identifiers to decode.
- **One clean API.** A handful of methods, no aliases, no hidden nuance. You won't find `andReturn()` sitting next to `returns()`, or `shouldReceive()` next to `expects()`. If you can guess the method name, you're probably right.
- **Ready for contribution.** Small, well-bounded internals mean no reverse-engineering the whole library for your first PR. New capability gets added once real use shows it's needed, not speculatively.

Code speaks louder than words:

```php
use JMac\Testing\TestDouble;

$repository = TestDouble::for(BookRepository::class);
$repository->expects('find')->with(123)->returns($book);

$service = new CatalogService($repository);
$service->lookup(123);

$repository->received('recordView')->with($book);
```

Testing often means providing a stand-in for a real dependency — a fake `BookRepository` that behaves how you need it to, without touching a real database. This library calls that stand-in a **test double**, and the snippet above is the shape of most tests you'll write with it: create a double, describe what you expect from it, run your code, and check what it did.

## One Verb per Concept

Every fluent method reads like a plain sentence, and there's exactly one way to say each thing:

```php
$repository->expects('find')->with(123)->returns($book);   // called exactly once
$repository->allows('save')->returns(true);                // called any number of times

$repository->allows('find')->with(999)->throws(new NotFoundException());

$repository->save($book);
$repository->received('save')->with($book);                // check it after the fact
```

If you've used Mockery or PHPUnit's built-in mocking before, the ideas here will already feel familiar — the words are just tidied up. [Migrating from Mockery](09-migrating-from-mockery.md) has a full side-by-side reference if you're coming from there.

## Where to Next

- [Installation](02-installation.md) — get it into your project.
- [Creating Test Doubles](03-creating-test-doubles.md) — make your first double and choose how it behaves.
- [Expectations](04-expectations.md) — the verbs you'll reach for in nearly every test.
