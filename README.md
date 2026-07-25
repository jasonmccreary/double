# Test Double

<p align="right">
    <a href="https://github.com/jasonmccreary/test-double/actions"><img src="https://github.com/jasonmccreary/test-double/workflows/CI/badge.svg" alt="Build Status"></a>
    <a href="https://packagist.org/packages/jasonmccreary/test-double"><img src="https://poser.pugx.org/jasonmccreary/test-double/v/stable.svg" alt="Latest Stable Version"></a>
    <a href="https://github.com/jasonmccreary/test-double/blob/master/LICENSE"><img src="https://poser.pugx.org/jasonmccreary/test-double/license.svg" alt="License"></a>
</p>

A modern, human-friendly PHP test double library — built as an alternative
to Mockery, for three reasons: Mockery's syntax leads with a taxonomy (Mock
vs. Spy vs. Partial Mock) that only makes sense to someone who already
understands test doubles; its failure messages are terse, name internal
generated class identifiers, and don't tell you what to do about the
failure; and it's slow-moving, under-contributed, and its internals
discourage new contributors from touching either the matching engine or the
error output. This library picks one verb per concept, no aliases, and
messages that tell you what to fix — see [Migrating from
Mockery](https://testdoublephp.com/migrating-from-mockery) if you're coming from there.

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
matching, verification, failure messages, PHPUnit integration, and
contributing — live at [testdoublephp.com](https://testdoublephp.com/).

## Contributing

Contributions should target the `master` branch, follow the project's
code style, and include tests. See [Contributing](https://testdoublephp.com/contributing)
for the project's standing policies (no aliases, module boundaries,
frozen public API) and walkthroughs for adding a matcher or improving a
failure message.
