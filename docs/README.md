# Documentation

Building the site: from this directory, run `composer install` once, then
`composer build` to render these chapters into `build/*.html`. The page
shell lives in `templates/*.html`, and hand-maintained assets (CSS, etc.)
live in `assets/` — both are plain files you can edit directly; `build/`
is regenerated from them and from the Markdown chapters on every run. Set
`TORCHLIGHT_TOKEN` (from https://torchlight.dev) in the environment first
to get syntax-highlighted code blocks; without it, code renders as plain
text.

This directory has its own `composer.json`, separate from the library's,
since it's expected to move into its own project eventually.

1. [Introduction](01-introduction.md)
2. [Installation](02-installation.md)
3. [Creating Test Doubles](03-creating-test-doubles.md) (includes Modes: Loose, Strict, Passthru)
4. [Expectations](04-expectations.md)
5. [Argument Matching](05-argument-matching.md)
6. [Verification](06-verification.md)
7. [Failure Messages](07-failure-messages.md)
8. [PHPUnit Integration](08-phpunit-integration.md)
9. [Migrating from Mockery](09-migrating-from-mockery.md)
