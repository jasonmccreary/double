# Introduction

Testing often means providing a stand-in for a real dependency — a fake
`BookRepository` that behaves how you need it to, without touching a real
database. This library calls that stand-in a **test double**, and gives
you one consistent way to create, configure, and check one.

```php
use JMac\Testing\TestDouble;

$repository = TestDouble::for(BookRepository::class);

$repository->expects('find')->with(123)->returns($book);

$service = new CatalogService($repository);
$service->lookup(123);

$repository->verify();
```

That's the shape of most tests you'll write with this library: create a
double, describe what you expect from it, run your code, and verify.
There's no separate class or constructor to choose between for a mock
versus a spy versus a partial mock. Every double behaves the same way, and
you add whatever behavior a test needs as you go — stub a return value
here, assert a call happened there, fall back to a real object where it's
useful.

## One Verb per Concept

Every fluent method reads like a plain sentence, and there's exactly one
way to say each thing:

```php
$repository->expects('find')->with(123)->returns($book);   // called exactly once
$repository->allows('save')->returns(true);                // called any number of times

$repository->allows('find')->with(999)->throws(new NotFoundException());

$repository->save($book);
$repository->received('save')->with($book);                // check it after the fact
```

You won't find `andReturn()` sitting next to `returns()`, or
`shouldReceive()` next to `expects()`. If you've used Mockery or PHPUnit's
built-in mocking before, the ideas here will already feel familiar — the
words are just tidied up. [Migrating from Mockery](09-migrating-from-mockery.md)
has a full side-by-side reference if you're coming from there.

## What Makes a Good Test Double

A few ideas run through the rest of these docs:

- **You shouldn't need a glossary to get started.** A test double is just a
  test double. Whatever behavior it needs — returning a value, throwing an
  exception, delegating to a real object — you add to the same object, the
  same way, every time.
- **A failure message should tell you what to do next.** When an
  expectation isn't met, the message names the double, names the call, and
  points at the fix — not just that something, somewhere, didn't match.
- **Small and easy to read beats exhaustive.** The matcher set, the
  configuration verbs, and the modes are deliberately minimal. New
  capability gets added once real use shows it's needed, not
  speculatively.

## Where to Next

- [Installation](02-installation.md) — get it into your project.
- [Creating Test Doubles](03-creating-test-doubles.md) — make your first
  double and choose how it behaves.
- [Expectations](04-expectations.md) — the verbs you'll reach for in
  nearly every test.
