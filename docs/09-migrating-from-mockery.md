# Migrating from Mockery

Most of what you already know from Mockery carries over directly — this page maps the concepts you're used to onto the equivalent here.

## Quick Reference

| Mockery | This Library |
|---|---|
| `Mockery::mock(Foo::class)` | `Double::for(Foo::class)` |
| `Mockery::spy(Foo::class)` | `Double::for(Foo::class)` — spy-style checking is `received()`, available on every double (see [below](#theres-no-separate-spy)) |
| `Mockery::mock()->shouldIgnoreMissing()` | `Double::for(Foo::class)` — this is simply the default, see [Modes](03-creating-doubles.md#modes) |
| `Mockery::mock(Foo::class, [$args])->shouldDeferMissing()` | `Double::for(Foo::class)->passthru($realInstance)` |
| `shouldReceive('foo')->once()->andReturn($x)` | `expects('foo')->returns($x)` — exactly-once is `expects()`'s default |
| `shouldReceive('foo')->andReturn($x)` | `allows('foo')->returns($x)` |
| `shouldReceive('foo')->andReturn($a, $b)` | `allows('foo')->returns($a, $b)` |
| `shouldReceive('foo')->andThrow($e)` | `allows('foo')->throws($e)` |
| `shouldReceive('foo')->andReturnUsing($fn)` | `allows('foo')->resolves($fn)` |
| `shouldHaveReceived('foo')` | `received('foo')` |
| `shouldNotHaveReceived('foo')` | `received('foo')->never()` |
| `shouldNotHaveBeenCalled()` | `unused()` — see [the trap below](#theres-no-separate-spy) |
| `once()` / `twice()` | `times(1)` / `times(2)` |
| `atLeast()->times($n)` | `times(minimum: $n)` |
| `atMost()->times($n)` | `times(maximum: $n)` |
| `between($min, $max)` | `times($min, $max)` |
| `ordered()` | `inOrder()` |
| `globally()` | not available — ordering applies per double, see [below](#ordering) |
| `byDefault()` | not available — see [below](#a-few-things-that-didnt-carry-over) |
| `Mockery::close()` | `$double->verify()`, or `use VerifiesDoubles;` — see [PHPUnit Integration](08-phpunit-integration.md) |

## Argument Matchers

| Mockery | This Library |
|---|---|
| `Mockery::any()` | `Argument::any()` |
| `Mockery::type($type)` | `Argument::type($type)` |
| `Mockery::on($callback)` | `Argument::satisfies($callback)` |
| `Mockery::capture()` | `Argument::capture($reference)` |
| `Mockery::pattern($regex)` | `Argument::matches($regex)` |
| `Mockery::anyOf($a, $b)` | `Argument::any($a, $b)` |
| `Mockery::notAnyOf($a, $b)` | `Argument::not()->any($a, $b)` |
| `Mockery::not($value)` | `Argument::not($value)` |
| `Mockery::contains(...)` / `hasKey(...)` / `hasValue(...)` | `Argument::contains(...)` — see [Searching a Collection](05-argument-matching.md#searching-a-collection) |
| `Mockery::mustBe($value)` / `isEqual($value)` | a plain value passed to `with()` — already the default |
| `Mockery::isSame($value)` | `Argument::same($value)` |
| `Mockery::ducktype(...)` | not available — see [below](#a-few-things-that-didnt-carry-over) |
| `andAnyOtherArgs()` | `Argument::remaining()` |
| `withNoArgs()` | `Argument::none()`, or `with()` with nothing passed — see [No Arguments at All](05-argument-matching.md#no-arguments-at-all) |

## Modes, Not Mock Kinds

Mockery starts with a choice: a mock, a spy, or a partial mock. Here, there's one kind of thing — a double — and the equivalent choice is a mode you add on top of it, covered fully in [Creating Doubles](03-creating-doubles.md):

- Mockery's plain `mock()`, once you call `shouldIgnoreMissing()`, behaves like **Loose** mode here — which is simply the default, nothing to opt into.
- A strict `mock()` with no leniency maps to **Strict** mode (`->strict()`).
- `shouldDeferMissing()` maps to **Passthru** mode (`->passthru($realInstance)`).

### There's No Separate "Spy"

In Mockery, `spy()` is its own constructor. Here, `received()` — checking whether something was actually called — is available on every double, regardless of how it was created or which mode it's in. You don't choose a "spy" up front; you reach for `received()` whenever you want to check after the fact, on the same double you'd otherwise configure with `expects()`/`allows()`. See [Verification](06-verification.md).

Watch out for `shouldNotHaveBeenCalled()` specifically: it reads like "this spy received no calls," but Mockery only checks whether the mock was invoked as a callable — it says nothing about calls to its methods, which is what most people actually mean and expect it to check. That's the trap `unused()` exists to close: it asserts the double received zero calls to any method, which is almost certainly what you meant in the first place.

## Ordering

Mockery orders calls per-mock by default, with `globally()` available for a single sequence shared across every mock in a test. This library keeps the per-double default and doesn't offer a global equivalent. If you find yourself needing a sequence that spans multiple doubles, it's worth pausing to consider whether the test is asserting more about call order than the behavior actually requires.

## A Few Things That Didn't Carry Over

A couple of Mockery features aren't available here, by design:

- **Aliases.** If you're used to `shouldReceive()`, `andReturn()`, or other alternate spellings for the same concept, those don't exist here — each concept has exactly one verb. See [One Verb per Concept](01-introduction.md#one-verb-per-concept).
- **`ducktype()`** — matching "anything with these methods" — isn't included. `Argument::satisfies()` covers the same need without a dedicated verb.
- **Static method mocking** (Mockery's `alias:` mocks). Mockery's own documentation already treats this as a last resort, and this library doesn't attempt to improve on it. See [Static Methods](04-expectations.md#static-methods).
- **`byDefault()`** — marking an expectation as a fallback that a later, more specific one can override. For the common case (an unbounded fallback, `allows()` with no `times()`/minimum), just register the fallback first and the override second: expectations are matched most-recently-registered first, falling back to earlier ones when a later one's `with()` constraints don't match the actual call. What doesn't carry over is Mockery's eviction behavior — a `byDefault()` expectation with its own call-count requirement has that requirement silently dropped once any other expectation exists for the method, even if that other expectation never actually matches a call. Here, every registered expectation's minimum stays in force and is checked at `verify()`.
