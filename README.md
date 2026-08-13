<p align="right">
    <a href="https://github.com/jasonmccreary/double/actions"><img src="https://github.com/jasonmccreary/double/workflows/CI/badge.svg" alt="Build Status"></a>
    <a href="https://packagist.org/packages/jasonmccreary/double"><img src="https://poser.pugx.org/jasonmccreary/double/v/stable.svg" alt="Latest Stable Version"></a>
    <a href="https://github.com/jasonmccreary/double/blob/master/LICENSE"><img src="https://poser.pugx.org/jasonmccreary/double/license.svg" alt="License"></a>
</p>


<picture>
  <source media="(prefers-color-scheme: dark)" srcset="docs/templates/assets/images/double-logo-wordmark-dark.svg">
  <img src="docs/templates/assets/images/double-logo-wordmark.svg" alt="Double" width="320" height="100">
</picture>

A modern PHP double library that puts developer experience first.

- **Zero learning curve** — create a `Double` and start testing immediately. No taxonomy to memorize, no upfront decisions about mocks vs. spies vs. partials.
- **Failures for humans** — no terse output, no internal class identifiers to decode, just a plain-English next step.
- **One clean API** — a handful of methods, no aliases, no hidden nuance. If you can guess the method name, you're probably right.
- **Ready for contribution** — small, well-bounded internals mean no reverse-engineering the whole library for your first PR.

## Installation

```sh
composer require --dev jasonmccreary/double
```

## Usage

```php
$repository = Double::for(BookRepository::class);
$repository->expects('find')->with(123)->returns($book);

$service = new CatalogService($repository);
$service->lookup(123);

$repository->received('recordView')->with($book);
```

## Documentation

Full docs — creating doubles, modes (Loose/Strict/Passthru), argument matching, verification, failure messages, PHPUnit integration, and contributing — live at [testdoublephp.com](https://testdoublephp.com/).

## Contributing

Contributions should target the `master` branch, follow the project's code style, and include tests. See [Contributing](https://testdoublephp.com/contributing) for the project's standing policies (no aliases, module boundaries, frozen public API) and walkthroughs for adding a matcher or improving a failure message.
