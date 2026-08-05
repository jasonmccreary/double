# Contributing

This page covers the standing policies behind the library's design, plus two walkthroughs for the contributions that come up most: adding a matcher and improving a failure message. Read the relevant section before sending a PR. A change that runs against one of these policies will need to be restructured before it can be merged.

## No Aliases

This library has exactly one canonical verb per concept: `expects()` / `allows()` for configuration, `returns()` / `throws()` / `resolves()` for outcomes, `with()` for argument constraints, counts (`times()`, `atLeastOnce()`, `never()`), and `received()` for spy-style assertions. There are no aliases for any of these, not for familiarity with Mockery, PHPUnit's native mocks, Prophecy, or Phake, and not ever. See [One Clean API](01-introduction.md#one-clean-api) for the reasoning, and [Migrating from Mockery](09-migrating-from-mockery.md) if you're looking for the familiar name instead of adding one.

Counts specifically: `times()` is overloaded (`times(3)` exact, `times(1, 3)` between, `times(minimum: 2)` at least, `times(maximum: 5)` at most) rather than growing into `once()`/`twice()`/`atMost()`/`between()` as separate words. `never()` is the one exception kept as its own verb rather than folded into `times(0)`.

A PR that adds a convenience alias (e.g. `andReturn()` next to `returns()`, or `shouldReceive()` next to `expects()`) will be declined, even though "add a familiar alias for people migrating from X" reads as helpful on its face. Every other PHP mocking library accumulated its API exactly this way: one library, four verbs for the same concept, forever. Migration help for people coming from Mockery or PHPUnit's native mocks belongs in documentation (a rosetta-stone table), not in the API surface.

If you think a concept is genuinely missing a verb (not an alias for one that already exists), open an issue to discuss it before sending a PR.

## Public API Stability

Two things are frozen as semver-guaranteed public API, not internal details that merely happen to be reachable: the `Matcher` interface (`matches()`, `describe()`, `explainMismatch()`) and the public readonly fields on every concrete `DoubleException` subclass. This extends to any value object one of those fields exposes. `UnsatisfiedExpectationException::$expectations` is a `list<Diagnostics\UnsatisfiedExpectation>`, for example, and `UnsatisfiedExpectation`'s own public readonly fields (`method`, `description`, `expectedMin`, `expectedMax`, `timesCalled`, `otherObservedCalls`) are frozen right along with it. A consumer reading `$exception->expectations[0]->description` is exactly the "structured access for anything that wants it" case this freeze exists for.

Practically: a PR widening `Matcher` itself, or renaming/removing/retyping a field on an existing exception (or on a value object one of those fields exposes), is a major-version change and needs to be flagged as one. Reach for an additive, optional interface (e.g. `ExplainsWithDetail extends Matcher`) instead when a matcher genuinely needs to convey more. `Argument` (the static facade) is not covered by this freeze and can keep growing incrementally.

## Module Boundaries

The codebase is split into `JMac\Testing\Engine`, `JMac\Testing\Matching`, `JMac\Testing\Diagnostics`, and `JMac\Testing\Exceptions`. Only `Engine` is allowed to depend on the others. `Diagnostics` has zero dependencies on the rest of the library. It's the shared home for rendering/formatting logic (`ValueFormatter`, `ArgumentFormatter`, `Pluralizer`) that more than one other module needs, specifically so that logic has exactly one implementation instead of being hand-duplicated per module. `Matching` and `Exceptions` each depend only on `Diagnostics`, nothing else. A PR that introduces a dependency pointing the wrong direction (e.g. `Matching` referencing `Engine` directly, or `Diagnostics` referencing anything) will need to be restructured before it can be merged.

## Walkthrough: Adding a Matcher

Every argument constraint (`Argument::any()`, `Argument::same()`, a predicate closure, and any future one) is a class in `src/Matching/` implementing the `Matcher` interface, plus one static entry point on the `Argument` facade (`src/Matching/Argument.php`). Concretely, using `SameMatcher` as the reference shape:

1. **Implement `Matcher`** in a new `src/Matching/YourMatcher.php`: `matches(mixed $actual): bool`, `describe(): string` (used in failure messages and in `with()`'s own argument description), and `explainMismatch(mixed $actual): ?string` (`null` when it matched; otherwise prose explaining why it didn't; see `SameMatcher::explainMismatch()` for the shape). Depend only on `JMac\Testing\Diagnostics\ValueFormatter` if you need to render a value. Nothing in `Engine`.
2. **Add a static entry point** on `Argument`, e.g. `public static function yourMatcher(mixed $expected): Matcher { return new YourMatcher($expected); }`. Don't add an alias for an existing matcher. See [No Aliases](#no-aliases) above.
3. **Unit test it** in `tests/Matching/YourMatcherTest.php`: cover `matches()` (true and false cases), `describe()`, and both branches of `explainMismatch()`. See `SameMatcherTest` for the pattern.
4. **Optionally**, if the matcher's `describe()` output should be proven inside a real rendered failure message, add a case to `tests/Exceptions/CallDescriptionMessagesTest.php` plus its golden file in `tests/fixtures/exceptions/describe-matcher-*.txt` (see [Walkthrough: Improving a Message](#walkthrough-improving-a-message) below for how golden files work).

Nothing else needs to change. `Matching` depends only on `Diagnostics`, and `ProxyBehavior`/`MethodExpectation` only ever talk to a constraint through the `Matcher` interface, never a concrete matcher class.

## Walkthrough: Improving a Message

Every exception's wording lives in one place: its `*Fields` trait in `src/Exceptions/` (e.g. `UnexpectedCallFields::renderMessage()`), shared between the plain exception and its `Integrations\PHPUnit\PHPUnitXxxException` sibling so both render identically with no separate edit. To change one:

1. **Edit the `render()`/`renderMessage()` method** on the relevant `*Fields` trait (or the constructor directly, for an exception with no PHPUnit sibling, e.g. `ModeConfigurationException`).
2. **Update the golden fixture(s)** in `tests/fixtures/exceptions/*.txt`. `GoldenFileTestCase::assertMatchesGolden()` does an exact string comparison against that file's contents, so a wording change will fail every test asserting against the old text until the corresponding `.txt` file is updated to match. There is no `--update-snapshots` flag; edit the fixture by hand (or regenerate it by printing the exception's new `getMessage()` and pasting it in).
3. **Run the golden-file suite** that covers it: `tests/Exceptions/ExceptionMessagesTest.php`, `CallDescriptionMessagesTest.php`, or `ValidationMessagesTest.php`, depending on which exception you touched. Confirm the new fixture is exactly right.
4. **Check `tests/Integrations/PHPUnit/PHPUnitExceptionsTest.php`** if the exception has a PHPUnit sibling: it asserts parity between the two, so it should pass without changes once step 1 is done (both read from the same trait), but it's the place a divergence would show up.
