# Creating Test Doubles

You may create a test double for any class or interface using
`TestDouble::for()`:

```php
use JMac\Testing\TestDouble;

$repository = TestDouble::for(BookRepository::class);
```

This returns a real object. It satisfies `instanceof BookRepository`, it
passes any type hint that expects one, and you may hand it straight to
whatever you're testing:

```php
$service = new CatalogService($repository);
```

Nothing about the double is configured yet — for that, see
[Expectations](04-expectations.md). This page covers the double itself:
how to create one, what it's called in failure messages, and how it
behaves before you've told it anything.

## Doubling More Than One Thing at Once

Sometimes the code you're testing needs a single object that satisfies
more than one contract — a logger that's also flushable, for example. You
may pass more than one target to `for()` to get one double implementing
all of them:

```php
$logger = TestDouble::for(LoggerInterface::class, FlushableInterface::class);

$logger instanceof LoggerInterface;    // true
$logger instanceof FlushableInterface; // true
```

Every target after the first must be an interface, the same rule PHP
applies to intersection types, since a class may only extend one parent.

## Wrapping a Real Instance

If you already have a real object in hand — built by a factory, resolved
from a container, or otherwise — you may pass the instance itself to
`for()` instead of a class name:

```php
$double = TestDouble::for($realBook)->passthru();
```

`TestDouble::for($realBook)` doubles `$realBook`'s class and remembers the
instance, so a later `->passthru()` call knows what to delegate to without
you needing to repeat yourself (`TestDouble::for(Book::class)->passthru($realBook)`
would work just as well). More on what `passthru()` does in
[Modes](#modes) below.

## Labels

Every double is given a label, derived from its class or interface name —
`BookRepository::class` becomes `"BookRepository"`. This label appears in
every failure message the double produces (see
[Failure Messages](07-failure-messages.md)), so you always know which
double is involved without hunting through generated class names.

## Reserved Method Names

Configuration lives directly on the double itself, which is what makes
`$repository->expects(...)` possible without a separate builder object.
The trade-off is that seven method names are reserved on every double:

```
expects, allows, strict, passthru, received, unused, verify
```

If the class or interface you're doubling declares a real method with one
of these names, `TestDouble::for()` throws right away, naming the exact
method:

```php
interface AuthorizerInterface
{
    public function allows(string $ability): bool;
}

TestDouble::for(AuthorizerInterface::class);
// Can't create a test double for "AuthorizerInterface": allows collides
// with TestDouble's own control verbs (expects/allows/strict/passthru/
// received/unused/verify) — a method can't be both a real one and a
// configuration verb.
```

This does come up in practice — `allows()` is part of Laravel's own
`Gate` contract, for instance — so it's worth knowing about early rather
than discovering it as a confusing test failure later.

## What Can't Be Doubled

A couple of things are rejected when you call `for()`, with a clear
reason, rather than failing in a confusing way later:

- **A `final` class.** There's nothing for the double to extend.
- **A class or interface that doesn't exist.** Usually a typo, or a
  missing `use`.

Static methods are a related, separate case: you may create a double for
a class that has one, but configuring it with
`expects()`/`allows()`/`received()` is rejected, since there's no instance
for a static call to run through. That's covered in
[Expectations](04-expectations.md#static-methods).

## Modes

Every double has exactly one mode, chosen either when you create it or on
the very next call, and it stays that way afterward. Mode answers one
question: **what happens when a call doesn't match anything you've
configured?** It never changes what `expects()`/`allows()` mean — those
work the same way in every mode.

### Loose (the Default)

```php
$repository = TestDouble::for(BookRepository::class);

$repository->allows('find')->with(123)->returns($book);

$repository->find(123); // $book
$repository->find(456); // a safe default — see below
```

This is what you get from a plain `TestDouble::for(...)`, with nothing
extra to opt into. An unconfigured call never throws — it returns a
sensible, type-safe value based on the method's declared return type:

| Return Type | What You Get |
|---|---|
| `void` | nothing |
| no type, nullable, or `mixed` | `null` |
| `bool` | `false` |
| `int` / `float` | `0` / `0.0` |
| `string` | `''` |
| `array` / `iterable` | `[]` |
| `self` / `static` | the double itself |
| an enum | its first case |
| a union type | the first branch that resolves cleanly, preferring `null` if it's an option |
| a non-nullable class or interface | a fresh double of that type, generated for you |

That last row is worth a moment: if `find()` is typed to return a
non-nullable `Author`, an unconfigured call doesn't hand you `null` and a
`TypeError` a few lines later — it hands you a real, freshly-generated
`Author` double instead. This only happens once per call chain. If that
generated double is then asked to produce something of its own, it stops
and asks you to configure it explicitly:

```
Test double "Book" only fabricates one call chain deep — configure
"author()" explicitly: $book->allows('author')->returns(...);
```

Loose mode doesn't stop you from configuring specific calls — you may
freely mix "stub these calls" with "fall back to a safe default for
everything else."

### Strict

```php
$repository = TestDouble::for(BookRepository::class)->strict();

$repository->allows('find')->with(123)->returns($book);

$repository->find(123); // $book
$repository->find(456); // throws — nothing was configured for this
```

Any call that doesn't match a configured expectation fails immediately,
by name. Reach for this when you'd rather a test fail the moment its
setup is incomplete, instead of discovering it indirectly further down.

### Passthru

```php
$logger = TestDouble::for(Logger::class)->passthru($realLogger);

$logger->info('hello');   // delegates to $realLogger->info('hello')
$logger->error('uh oh');  // still recorded, so received('error') works
```

Unconfigured calls delegate to a real object you supply. Anything you
have configured still intercepts as usual, and every call — whether it
was intercepted or delegated — is still recorded, so `received()` (see
[Verification](06-verification.md)) works the same as it does in any
other mode.

Calling `->passthru()` with no argument tries to build the real instance
for you through reflection, and explains plainly if that isn't possible:

```
Can't auto-instantiate "Logger" to passthru to: constructing it threw:
... Pass an existing instance instead: ->passthru($existingInstance).
```

Passthru only applies to classes, since an interface has no
implementation to delegate to.

If you only want one specific call to delegate to a real object, rather
than the whole double, `resolves()` is the better fit — see
[Expectations](04-expectations.md).
