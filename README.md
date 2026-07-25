# Test Double

A modern, human-friendly PHP test double library — built as an alternative
to Mockery, for three reasons: Mockery's syntax leads with a taxonomy (Mock
vs. Spy vs. Partial Mock) that only makes sense to someone who already
understands test doubles; its failure messages are terse, name internal
generated class identifiers, and don't tell you what to do about the
failure; and it's slow-moving, under-contributed, and its internals
discourage new contributors from touching either the matching engine or the
error output. This library picks one verb per concept, no aliases, and
messages that tell you what to fix — see [Migrating from
Mockery](docs/09-migrating-from-mockery.md) if you're coming from there.

## Installation

```sh
composer require --dev jasonmccreary/test-double
```

## Usage

```php
use JMac\Testing\TestDouble;

$repository = TestDouble::for(BookRepository::class);

$repository->expects('find')->with(123)->returns($book);

$service = new CatalogService($repository);
$service->lookup(123);

$repository->verify();
```

That's the shape of most tests you'll write with this library: create a
double, describe what you expect from it, run your code, and verify. There's
no separate class or constructor to choose between for a mock versus a spy
versus a partial mock — every double behaves the same way, and you add
whatever behavior a test needs as you go.

```php
$repository->allows('save')->returns(true);                // any number of calls, including zero
$repository->allows('find')->with(999)->throws(new NotFoundException());

$repository->save($book);
$repository->received('save')->with($book);                // check it after the fact
```

`TestDouble::for()` also accepts an already-built real instance via
`->passthru()`, so unconfigured calls delegate to it while configured ones
still intercept:

```php
$double = TestDouble::for($realLogger)->passthru();
```

## Documentation

Full docs — creating doubles, modes (Loose/Strict/Passthru), argument
matching, verification, failure messages, and PHPUnit integration — live in
[docs/](docs/README.md). See [CONTRIBUTING.md](CONTRIBUTING.md) for the
project's standing policies (no aliases, module boundaries, frozen public
API) before sending a PR.
