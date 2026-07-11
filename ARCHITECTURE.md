# Architecture

This document captures the design decisions behind this library — a modern,
human-friendly PHP mocking library built as an alternative to Mockery. It's
written for whoever picks up implementation next (including an AI coding
agent), so it includes not just *what* was decided but *why*, and flags what
is still explicitly open.

## Motivating gripes (the whole reason this project exists)

1. Mockery's syntax leads with a taxonomy (Mock vs Spy vs Partial Mock) that
   only makes sense to someone who already understands test doubles.
2. Mockery's failure messages are terse, name internal generated class
   identifiers, and don't tell you what to do about the failure.
3. Mockery is slow-moving, under-contributed, and its internals discourage
   new contributors from touching either the matching engine or the error
   output.

Every decision below traces back to one of these three.

## License and repo structure

- **License: MIT.**
- **One repository.** Core library, mode system, matchers, diagnostics,
  exceptions, *and* PHPUnit integration all live in this repo and ship
  together — see "PHPUnit integration" below for why this changed from an
  earlier plan to split it out.
- **The Laravel integration is the one thing that stays a separate package
  and repo**, and deliberately so: it's a real dependency on Laravel's
  service container, config system, and possibly its own test helpers — a
  full framework integration with its own release cadence and audience, not
  a conditionally-loaded exception subclass like the PHPUnit case.
- **Minimum PHP version: 8.3.** 8.3 still has roughly a year and a half of
  active support left, so the floor stays there rather than jumping to 8.4
  and cutting off users on the still-supported prior release.
  Concrete consequence, not just a version-number choice: PHP 8.4
  deprecated implicit nullable parameters (`Type $x = null` without an
  explicit `?Type`). `ClassGenerator::buildParam()` must always generate
  the explicit `?Type $x = null` — never the bare form — or the library's
  own test suite emits deprecation noise on every run under 8.4+, even
  though the floor itself is 8.3. PHP 8.4 also introduced property hooks
  and asymmetric visibility, and interfaces can now declare property hooks
  as part of their contract; `ClassGenerator` currently only reasons about
  methods, so a target interface with hooked properties is a real gap —
  filed alongside the other known scaffold limitations below (magic
  methods, final classes) rather than scoped into M1.
- **CI matrix: PHP 8.3, 8.4, and 8.5**, floor and current stable both covered.
  Add an allowed-to-fail job against PHP 8.6 (in active development,
  expected toward the end of 2026) once alpha/nightly builds exist —
  `ClassGenerator`'s reflection-based codegen is exactly the kind of code
  that breaks first on a new PHP version, so catching that early is worth
  the extra job even this far ahead of release.
- **Core has zero mandatory runtime dependencies.** `phpunit/phpunit` is a
  `require-dev` dependency only, never `require` — see "PHPUnit integration."

## API philosophy

One canonical verb per concept. No aliases — not `shouldReceive()` sitting
next to `expects()`, not `andReturn()` next to `returns()`, not for
Mockery-migration familiarity, not ever. Every one of Mockery, PHPUnit's
native mocks, Prophecy, and Phake invented its own verb set for the same
underlying concepts; the accumulation of "convenience alias for people
coming from X" is exactly how a library ends up with four ways to say the
same thing. This is a standing policy, not a one-time decision — it needs to
be written into `CONTRIBUTING.md` explicitly, because "add a familiar alias"
is the kind of PR that looks helpful on its face and needs a stated reason
to decline.

Migration help for people coming from Mockery/PHPUnit belongs in a
documentation rosetta-stone table, not in the API surface:

| Mockery | PHPUnit native | This library |
|---|---|---|
| `shouldReceive('foo')->once()->andReturn($x)` | `expects($this->once())->method('foo')->willReturn($x)` | `expects('foo')->returns($x)->once()` |
| `shouldReceive('foo')->andReturn($x)` | `method('foo')->willReturn($x)` | `allows('foo')->returns($x)` |
| `shouldHaveReceived('foo')` (spy) | — | `$double->received('foo')` |

### Verb lineage

The verb choices mirror RSpec and Mockery specifically (not invented from
scratch), with Eloquent-style conventions layered on for expressiveness:
static factory entry point, fluent chains that read as sentences, no "and"
prefixes on chained calls (`returns()`/`throws()`, not `andReturn()`/
`andThrow()` — Eloquent doesn't do `andWhere()`, chaining already implies
"and").

```php
$repo = TestDouble::for(BookRepository::class);

// expects()/allows() are literally RSpec's expect().to receive() /
// allow().to receive(), folded into one verb since PHP has no block syntax
// to hang ".to receive()" off of.
$repo->expects('find')->with(123)->returns($book)->once();
$repo->allows('save')->returns(true);

// sequential returns on repeated identical calls — RSpec's and_return(1, 2, 3)
// and Mockery's andReturn(1, 2, 3): first call gets $first, second gets
// $second, third+ holds at $second. This is what resolves the ordering
// question below — sequencing is a property of ONE expectation, never
// modeled as multiple competing expectations.
$repo->allows('find')->with(1)->returns($first, $second);

$repo->allows('find')->with(999)->throws(new NotFoundException());

// counts, unchanged from RSpec/Mockery
$repo->expects('save')->once();      // also the DEFAULT for expects() — see below
$repo->expects('save')->twice();
$repo->expects('save')->times(3);
$repo->expects('save')->atLeastOnce();
$repo->allows('save')->never();      // no separate "reject" verb needed

// partial mock — passthru() is Mockery's actual real per-expectation method
// name for "call the real implementation for this one stub." Reused
// authentically, not coined.
$repo->allows('log')->passthru();
$fullDouble = TestDouble::for(Logger::class)->passthru($realLogger);

// spy-style assertion — lives directly on the double (Eloquent-style),
// not behind a separate TestDouble::assert() facade
$repo->received('save')->with($book);
$repo->received('save')->times(1);
$repo->received('save')->never();

// argument matchers get their own facade, named plainly after the concept
// they represent (an argument constraint) rather than testing jargon —
// see "Argument matcher facade naming" below for why TestMatch was rejected
// and Argument was chosen instead
$repo->allows('find')->with(Argument::any())->returns($book);
$repo->allows('save')->with(Argument::type(Book::class))->returns(true);
$repo->allows('find')->with(Argument::satisfies(fn ($id) => $id > 100))->returns($book);
```

Starting matcher set: `Argument::any()`, `Argument::type($class)`,
`Argument::satisfies($predicate)`. Deliberately minimal for v1 — add more
once real usage shows what's actually needed, rather than porting
RSpec/Mockery's full matcher catalog speculatively.

### Argument::satisfies()

Started as `that()` (matching Prophecy's own name for this matcher — see
"Argument matcher facade naming" above). Revisited: read backwards, `any()`
and `capture()` both form a complete, self-explanatory two-word phrase —
"any argument," "capture argument" — with nothing implied. `that()` doesn't;
"that argument" reads as pointing at a specific argument, not "an argument
such that a condition holds," which is what the method actually means.
`type()` has the same gap ("type argument" needs an implied "of") but
wasn't revisited here, since a class-instance check reads unambiguously
enough at the call site regardless.

Candidates considered and rejected: `on()` (Mockery's name for this,
rejected on principle — see `CONTRIBUTING.md`'s no-familiarity-aliasing
policy); `matches()` (collides with `Matcher::matches()`, the method every
matcher — including this one's own instance — already implements, so the
same word would mean two different things one line apart); `check()`,
`vet()`, `screen()`, `qualify()`, `accept()`, `confirm()`, `validate()`,
`ensure()` (each either too technical/assertion-flavored, implied a
throw/rejection this matcher doesn't do, or just didn't read naturally
in place).

**Decision: `satisfies()`.** Matches RSpec's own generic predicate matcher
(`expect(value).to satisfy { |v| ... }`) — consistent with this document's
existing use of RSpec as prior art elsewhere (see the `expects()`
default-to-once behavior in "Sensible defaults"). Reads correctly in the
order it's written, no reversal needed: `Argument::satisfies($predicate)`
is literally "the argument satisfies this predicate."

`Argument::capture(&$reference)` is the first post-v1 addition, once real
usage showed up — this library's maintainer co-authored the equivalent
`Mockery::capture()` feature upstream, so it's adopted directly as the
closest, most relevant prior art rather than reinvented. Like `any()`, it
matches any value; unlike `any()`, it also writes the value into
`$reference` once its expectation is confirmed as the real match:

```php
$captured = null;
$repo->allows('save')->with(Argument::capture($captured))->returns(true);

$repo->save($book);

$this->assertSame($book, $captured);
```

`$reference` only ever holds the most recently matched call's value — it's
overwritten each time, there's no history of earlier calls. Matches
Mockery's documented behavior (no multi-call retrieval), and keeps the
return type `Matcher`, same as `any()`/`type()`/`satisfies()`, since the caller
never needs to hold onto the matcher itself — the reference variable is the
handle.

**Capturing can't happen inside `matches()`.** `ProxyBehavior::findMatch()`
calls `matchesArguments()` — and therefore each argument's `matches()` — on
every candidate expectation it tries, newest first, until one fully
matches; a candidate that matches on an early position but fails on a later
one has still had `matches()` called on that early position. If capturing
were a side effect of `matches()`, a losing candidate could record a value
for a call it never actually served. So `matches()` stays pure (safe to
call speculatively, any number of times), and capturing instead happens in
`MethodExpectation::recordMatch()`, called exactly once, only on the
expectation `findMatch()` has already confirmed is the real match for this
call.

### Argument matcher facade naming

The facade started life as `TestMatch`, named (per "Verb lineage" above) to
echo `TestDouble` itself. Revisited and rejected: `TestMatch` reads as
testing jargon — a newcomer has to already know this library's naming
conventions to parse it — and "echoes TestDouble" isn't a strong enough
reason on its own, since the object it produces isn't a double, it's a
constraint on one argument to a call.

The real alternative considered was merging these three static methods
directly onto `TestDouble` (`TestDouble::any()`, `TestDouble::type()`,
`TestDouble::that()`), removing a class from the public API entirely.
Rejected: `with(TestDouble::any())` reads worse than either name it's
replacing — "TestDouble" names the mock object, not a constraint placed on
an argument to it — and it would be the one place this library backs into
the single-god-class pattern (`Mockery::mock()`/`any()`/`type()` all on one
class) that the module-boundary design elsewhere in this document
deliberately avoids.

**Decision: rename to `Argument`.** Matches `Prophecy\Argument` — an
existing PHP mocking library with the identical `any()`/`type()`/`that()`
matcher API under this exact name — so this isn't a novel coinage, it's
adopting the closest, most relevant prior art available (closer than
Mockery or RSpec here, since it's specifically about naming an
argument-matcher facade, not verb choice). `with(Argument::any())` also
reads as plain English at the call site: "with argument: any."

(The third verb itself later diverged from Prophecy's `that()` — renamed to
`satisfies()`, see "Argument::satisfies()" below — but the facade name
decision here is unaffected.)

### Expectation matching order: last-registered-that-matches wins

Resolved decision, and a real bug fix over an earlier draft. The natural
authoring pattern is "declare a broad default, then layer specific overrides
on top":

```php
$repo->allows('find')->returns(null);               // default, declared first
$repo->allows('find')->with(123)->returns($book);   // specific override, declared second
```

If expectations are checked in *registration* order, the broad rule
(matches any arguments) shadows the specific one forever, since it's tried
first and never exhausted — the override becomes unreachable. That's
backwards from how people write test setup. **The rule is: when a call
could match more than one configured expectation, the most recently
registered one wins.** This is positional rather than "smart" (no attempt to
rank by matcher specificity), deliberately — a rule you can state as "read
your setup top to bottom, the last applicable line wins" is something a
newcomer can hold in their head without understanding matcher internals,
which matters more here than a cleverer-but-unpredictable specificity
ranking would.

This is exactly why sequential returns had to become a `->returns($a, $b)`
variadic feature on one expectation rather than multiple competing
expectation objects with `->once()` + exhaustion-based fallthrough (the
mechanism the early throwaway scaffold used) — that mechanism needed
first-registered-priority to work, which directly contradicts
last-wins. Pulling sequencing out into its own feature removes the conflict
entirely rather than needing an exception carved into the ordering rule.

## Sensible defaults — a general principle, not a list of special cases

**Every fluent modifier needs a default that makes the bare verb a complete,
correct sentence on its own.** Audited against the full verb set:

| Call | Default if the modifier is omitted |
|---|---|
| `expects('foo')` | must be called exactly once (matches RSpec's own default for `expect().to receive()`) |
| `allows('foo')` | may be called any number of times, including zero |
| `->with(...)` omitted | matches any arguments |
| `->returns(...)` omitted | **the safe-default-by-return-type table (see Modes below), applied uniformly** |
| `TestDouble::for(X::class)` | label auto-derived from the class name |
| mode | Loose |

The one real gap this audit found: an expectation that matches but never
had `->returns()`/`->throws()`/`->resolves()` configured was defaulting
to plain `null` unconditionally — the exact same "return type isn't
nullable, so this is a `TypeError` waiting to happen" problem Loose mode's
fallback path already had to solve. **Fix: there is only one
safe-default-by-return-type resolver in the codebase, used at both call
sites** — Loose mode's unmatched-call fallback, and any matched expectation
missing an explicit return. Same table, same recursive fabrication for
non-nullable object returns, same depth cap, same provenance tagging. This
deletes a special case (the old "just return null" shortcut) rather than
adding one.

## Modes: Loose, Strict, Passthru

A double has exactly one mode, set once at creation and immutable after
(setting more than one is a setup-time configuration error, not a silent
override). Mode governs **only** the fallback path taken when a call
matches no configured expectation — it never changes what `expects()` /
`allows()` themselves mean.

### Loose (default)

`allows()` still works inside a Loose double — Loose is not a Mockery-style
"pure spy" that forbids configuring return values; you can freely mix
"stub a couple of specific calls" with "let everything else through."

For a call matching no expectation, Loose returns a safe default based on
the method's declared return type (the same resolver described above):

| Return type | Safe default |
|---|---|
| `void` | nothing returned |
| no type / nullable / `mixed` | `null` |
| `bool` | `false` |
| `int` / `float` | `0` / `0.0` |
| `string` | `''` |
| `array` / `iterable` | `[]` |
| `self` / `static` | the double itself |
| enum | first case |
| union | first branch that resolves cleanly; prefer `null` if present |
| intersection | a fabricated double implementing all constituent interfaces |
| **non-nullable class/interface** | **a fresh Loose-mode double of that type, fabricated once — see the fabrication limit below for what happens past that** |

Guardrails on fabrication, both mandatory:

- **A real, hard fabrication limit — one free hop, not a "proposed default
  2, configurable" soft cap.** An earlier draft of this section proposed a
  depth-2 cap described as "configurable" and, in the first implementation,
  a depth check that didn't actually stop anything: past the cap, a
  non-cyclic chain of distinct fabricated types just kept fabricating one
  level further "anyway," so the limit was cosmetic — a sufficiently deep
  unconfigured call chain would recurse indefinitely, silently, with a fresh
  `eval()`'d class per hop (`ClassGenerator` doesn't cache generated
  classes). That's worse than a bare `TypeError`, not better: an unbounded,
  silent cost with no diagnostic pointing at the actual gap in test setup —
  exactly the failure mode this library exists to avoid (see the motivating
  gripes at the top of this document). **Revised: `MAX_FABRICATION_DEPTH =
  1`**, matching the single free, safely-typed hop Mockery's own
  `shouldIgnoreMissing()` fallback gets before *it* stops being type-aware
  (see the Mockery comparison below) — one unconfigured call may fabricate
  a correctly-typed stand-in for free, but a second, distinct fabrication
  needed off of *that* stand-in throws `FabricationLimitExceededException`
  (a mid-test failure, with a PHPUnit `AssertionFailedError` sibling, same
  treatment as `UnexpectedCallException`), naming the double and the call
  that needed to go deeper and telling the person to configure it explicitly
  — deliberately plain, human prose ("Test double X only fabricates one call
  chain deep...") over a denser message reciting every internal detail
  (mode name, the specific type that couldn't be fabricated, the numeric
  limit) the exception object still carries as fields for anything that
  wants to inspect it programmatically, just not in the prose itself. Not
  exposed as a per-double knob — there is no constructor/verb for it, and
  none is planned; a single, predictable,
  identically-enforced default was judged more valuable than a tunable one,
  consistent with this document's broader "no configuration for its own
  sake" bias.
  - **Exception, not limit:** a self-referential return (`self`, `static`,
    or a return type literally naming the method's own declaring
    class/interface — e.g. `NodeInterface::next(): NodeInterface`) never
    fabricates at all; it always resolves to the current double itself. This
    path is unaffected by the limit above and can be called any number of
    times.
  - **Cycle-closing, not an exception to the limit:** if, once the limit is
    reached, the double being asked to return something already satisfies
    the required type, it's reused directly instead of throwing — a true
    cycle costs nothing further, only a *distinct*, non-cyclic chain of
    fabricated types hits the exception.
- **Mandatory provenance tagging** on every fabricated object, so if
  anything ever inspects one (a failed assertion, a dump), the message
  explains it's a stand-in rather than leaving the person to guess why a
  value looks wrong.

**Confirmed against Mockery's actual source (1.6.x), not assumed:** an
earlier draft of this section claimed `Mockery::spy()`'s fallback is
unconditionally `null` regardless of declared return type. That's wrong and
worth correcting rather than leaving on record. `Mockery::spy()` is
`Mockery::mock()->shouldIgnoreMissing()`, and `shouldIgnoreMissing()`'s
default fallback (`Mock::mockery_returnValueForMethod()`) is already
return-type-aware — `''`/`0`/`0.0`/`false`/`[]`/`void → null`/`static →
$this`, the same shape as this library's own table, and for a non-nullable
class/interface return it fabricates a real `Mockery::mock($returnType)`
rather than `null`. So the first hop doesn't produce a bare-`null`
`TypeError` the way the earlier draft implied.

**Where Mockery's approach actually differs, and what this library now
deliberately mirrors:** that fabricated nested mock is a *plain* mock, not
itself a spy — recursion only continues if `shouldIgnoreMissing($value,
true)` was called with `$recursive = true`, which `spy()` itself never
passes, so it defaults to `false`. One hop past the first fabrication, an
unmatched call throws Mockery's own `BadMethodCallException` ("Received
`Mockery_X::y()`, but no expectations were specified"). That's a real,
identifiable failure — just one hop later than a naive reading suggests, and
via an exception rather than a silent `null`. **This library's revised
`MAX_FABRICATION_DEPTH = 1` (see "Guardrails on fabrication" above)
deliberately matches that same one-free-hop boundary**, rather than trying
to out-engineer it with deeper automatic recursion: the earlier depth-2,
"fabricate one level further anyway past the cap" design was strictly worse
than Mockery's simpler model, not better — it recursed further *and* failed
less legibly when it finally gave up (silent, unbounded `eval()` cost, no
diagnostic) than Mockery's plain, if terse, exception does. What this
library keeps as a genuine improvement over Mockery, rather than an
elaboration on it, is entirely in the message:
`FabricationLimitExceededException` names the double, the method, the exact
type that couldn't be fabricated, and what to do about it, versus Mockery's
generic "no expectations were specified."

### Strict

Any unmatched call throws immediately with the full diagnostic treatment —
no fabrication, no defaults.

### Passthru

`->passthru($realInstance)` wraps an object you already constructed;
unconfigured calls delegate to it, configured ones still intercept, and
delegated calls are still recorded for spy-style assertions.
`->passthru()` with no args attempts reflection-based auto-instantiation,
throwing a clear setup-time error suggesting `->passthru($existingInstance)`
if that fails. Only valid for classes, not interfaces — validated at setup
time.

## Class surface area — reserved names on the generated double (resolved)

Because configuration verbs live directly on the double itself (fluent,
Eloquent-style chaining, not a separate facade), five real method names are
reserved on every generated double: `expects`, `allows`, `strict`,
`passthru`, `received`. A target type that happens to declare a real method
with one of these names collides.

**Decision: keep all five as real instance methods. Do not try to engineer
this risk down to zero.** Two alternatives were seriously considered and
rejected:

- **A "pending" double that defers real class generation until the first
  `expects()`/`allows()` call**, so `strict`/`passthru` wouldn't need to be
  real methods until then. Rejected: it doesn't survive the single most
  common usage pattern this library is built around — creating a double
  and handing it to a real, type-hinted constructor with zero configuration
  (`new CatalogService(TestDouble::for(BookRepository::class))`). PHP checks
  the type hint at the moment of that call, not lazily, so there is no
  later point at which "rendering" could still happen in time.
- **Moving `strict`/`passthru` to named constructor arguments**
  (`TestDouble::for(Foo::class, mode: Mode::Strict)`), which does achieve
  zero collision risk for those two. Rejected because it was explicitly
  weighed against keeping the full fluent chain, and the fluent,
  human-readable syntax was judged more important than closing this
  particular gap — a deliberate priority call, not an oversight.

**The risk is real for all five, not just `strict`.** `allows()`
specifically is probably the single most likely collision in the entire
verb set: authorization/policy interfaces routinely declare a real
`allows($ability, ...$args): bool` method (Laravel's own `Gate` contract
uses this exact verb), so doubling an `AuthorizerInterface` hits this
immediately, in a mainstream testing scenario, not an edge case. `received()`
is plausible in messaging/delivery domains. `expects()` is the least likely
of the three but not zero (HTTP content-negotiation, e.g. Laravel's own
`Request::expectsJson()`).

**Confirmed via research, not assumed: Mockery already reserves the exact
same two names.** As of Mockery 1.0.0, `allows()` and `expects()` were
added as an alternative to `shouldReceive()`, and Mockery's own docs state
these names are now reserved — classes being mocked can't have real methods
called `allows` or `expects`. Mockery does **not** throw any dedicated
error when this happens; a real `expects()`/`allows()` method on the target
class is silently shadowed, with no indication to the person that it
happened. This is directly useful precedent: it confirms the trade-off is
livable at real-world scale (Mockery has shipped this for years), and it
means our planned mitigation is a concrete improvement over the incumbent's
actual behavior, not just a defensible compromise.

**Mitigation: a mandatory, specific collision check in `ClassGenerator`,
part of M1, not a later hardening pass.**

```php
final class ReservedNameCollisionException extends \LogicException {}

// inside ClassGenerator, before generating source:
$reserved = ['expects', 'allows', 'strict', 'passthru', 'received'];
$collisions = array_intersect($reserved, array_map(
    fn (\ReflectionMethod $m) => $m->getName(),
    $reflection->getMethods(\ReflectionMethod::IS_PUBLIC)
));

if ($collisions !== []) {
    throw new ReservedNameCollisionException(sprintf(
        'Cannot create a test double for "%s": it declares method(s) %s, '
        . 'which collide with TestDouble\'s own control API and cannot be '
        . 'both a real interface method and a configuration verb on the '
        . 'same object.',
        $target,
        implode(', ', $collisions)
    ));
}
```

Thrown at `TestDouble::for()` time, naming the exact colliding method(s).
No structural escape hatch (e.g. a wrapper object separating configuration
from the interface surface) is being built pre-emptively — that's real
design work for a case that should be confirmed common enough in practice
to justify it, not spun up speculatively.

**M1 test requirement:** one fixture interface per reserved name, each
declaring a real method with that exact name, asserting
`ReservedNameCollisionException` fires with the correct method named in the
message. Include an `AuthorizerInterface`-shaped fixture with a real
`allows()` specifically — this is a confirmed, not hypothetical, real-world
collision (both from the Laravel `Gate` precedent and from Mockery's own
documented experience with the identical name), and deserves its own
explicit test rather than being covered incidentally by a generic
`allows`-collision fixture.

This whole thread is now closed, not open — reserved list, rationale, and
mitigation are all decided.

## Matcher

Every argument constraint — a literal value, `Argument::any()`, a
predicate closure, and any future matcher — implements one interface:

```php
interface Matcher
{
    public function matches(mixed $actual): bool;
    public function describe(): string;
    public function explainMismatch(mixed $actual): ?string;  // null if it matched
}
```

A bare literal passed to `->with(123)` is wrapped in an internal
`EqualsMatcher` at the boundary so the engine treats every argument
uniformly. Adding a new matcher is a self-contained, three-method
contribution that never touches the engine, the diagnostics pipeline, or
the exception hierarchy.

Decision: `explainMismatch()` stays a plain `?string`. Candidate-ranking
(picking the "closest" expectation when several don't match) is built from
the match-count signal the assembler already has, not from richer matcher
return data. If a matcher genuinely needs to convey more structured detail
later, add an **optional** interface (`ExplainsWithDetail extends Matcher`)
rather than widening the mandatory contract — interface segregation keeps
the 95% case minimal forever.

## Module boundaries (the acyclic dependency rule)

```
Incoming call → [ Matcher ] → [ Diagnostic assembly ] → [ Renderer ]
```

**Only Engine (the syntax layer: `ClassGenerator`, `ProxyBehavior`,
`DoubleState`, `MethodExpectation`) is allowed to know about everything
else, and `TestDouble` — the top-level facade sitting in front of Engine —
inherits that same allowance.** `TestDouble` itself lives at the root
namespace (`JMac\Testing\TestDouble`), not nested under `Engine\`: it's the
one class every consumer of the library touches directly
(`TestDouble::for()`/`TestDouble::verify()`), so surfacing an internal
module name (`Engine`) in its own fully-qualified name would leak an
implementation detail into the most-seen part of the public API. This is a
namespace/discoverability change only — `TestDouble` still delegates to
Engine's internals exactly as before, and those internals stay `@internal`
under `JMac\Testing\Engine\*`. Every other module depends only on what's
strictly beneath it:

- **Matching** — zero dependencies on the rest of the library.
- **Diagnostics** — zero dependencies on the rest of the library. Shrunk
  down, on purpose, to just the `Diagnostic` marker interface and
  `UnsatisfiedExpectation` (a plain list-item value object with no exception
  of its own — see below). Both still hold plain strings/scalars only, never
  a `Matcher` reference.
- **Exceptions** — depends only on Diagnostics.
- **Integrations\PHPUnit** — a fourth module in this same sense: depends only
  on Exceptions (to extend `TestDoubleException`) and conditionally on
  PHPUnit's own classes. Nothing else in the codebase depends on it — Engine
  picks between "plain exception" and "PHPUnit exception" via a
  `class_exists` check without needing to know anything about
  `SelfDescribing` or `ComparisonFailure`.

**Revised (post-M4): no more one `XyzDiagnostic` class per `XyzException`.**
The original M3 design paired every exception with a same-shaped Diagnostic
data class purely so `Diagnostics` (zero deps) could stay decoupled from
`Exceptions` while a shared `DiagnosticRenderer` dispatched on the concrete
Diagnostic type. In practice the only way any code ever obtained a bare
`Diagnostic` was by calling `getDiagnostic()` on the exception that already
wrapped it — there was no independent producer — and only one renderer
(`PlainTextRenderer`) ever existed. That made the split pure ceremony: a new
failure mode meant a new `XyzDiagnostic` file, a new `XyzException` file,
and a new match-arm in `PlainTextRenderer`, all three always changing
together. **Each `TestDoubleException` subclass now holds its own diagnostic
fields directly and implements `Diagnostic` itself** (`getDiagnostic()`
returns `$this`), rendering its own `getMessage()` in its own constructor.
This resolves the "is `Diagnostic`'s shape a frozen public contract"
question that used to be an open decision here, in favor of "internal
detail, coupled to the exception hierarchy" rather than a
renderer-agnostic public contract — see "Exceptions and PHPUnit integration"
for the shape this produces and why it doesn't collide with M5.

**Still an open decision, must resolve before 1.0:** `Matcher`'s shape (is
it a frozen, semver-guaranteed public contract or an internal detail that
happens to be reachable?) is untouched by the above and still needs an
explicit call before `1.0.0`, not allowed to ossify by accident.

This is enforced with namespace-per-module (`JMac\Testing\Matching\*`,
`JMac\Testing\Diagnostics\*`, `JMac\Testing\Exceptions\*`,
`JMac\Testing\Integrations\PHPUnit\*`, `JMac\Testing\Engine\*`, with the
`TestDouble` facade itself sitting one level up at `JMac\Testing\TestDouble`)
plus `@internal` annotations on everything inside a module that isn't its
intended public surface, with a CI-enforced static analysis rule (Psalm's
built-in `@internal` enforcement, or an equivalent PHPStan rule) that fails
the build if code outside a module's namespace references one of its
`@internal` classes. Namespace discipline alone is a suggestion; the CI
check is what makes the boundary load-bearing rather than cosmetic.

## Correlating unsatisfied expectations with actual observed calls (top priority)

Motivated by the single most common real failure mode: `expects('bar')->with('baz')`
never fires because the actual code under test called `bar('Baz')` — a case
typo, a stale variable, a wrong value — and the `verify()` failure message
currently gives no hint that `bar()` was ever called with anything at all.

**This is deliberately not the same mechanism as the "closest candidate"
diffing in the unexpected-call path, and that distinction matters.** The
closest-candidate diff is a heuristic — it picks the first configured
expectation whose arguments don't line up and calls it "similar," which is
a judgment call that could point at the wrong thing. This is not that:
`DoubleState` already records every call made against a double regardless
of whether it matched anything, so "was this method called at all, with
something else" is a plain fact already sitting in the call log, not an
inference. **General principle: exact correlation (same method name) gets
stated as fact in a diagnostic; anything requiring a similarity judgment
gets phrased as speculation.** These should read differently in rendered
output so a person can tell which kind of claim they're looking at.

```php
final class UnsatisfiedExpectation
{
    public function __construct(
        public readonly string $description,
        public readonly int $expectedMin,
        public readonly int $expectedMax,
        public readonly int $timesCalled,
        /** @var string[] every other call actually observed for this
         *  method, regardless of whether it matched anything — plain
         *  fact, no diffing, no similarity ranking */
        public readonly array $otherObservedCalls,
    ) {}
}
```

Engine assembles this by pairing each unmet expectation from
`DoubleState::unmetRequiredExpectations()` with
`DoubleState::callsFor($expectation->method())` — both already exist today;
this is a correlation that was never being done at message-build time, not
new data collection.

Rendered example, matching the motivating scenario exactly:

```
1 expectation was not satisfied on test double "foo":

    bar("baz") — expected exactly 1 time(s), called 0 time(s)

    "bar" was called with different arguments elsewhere in this test:

        bar("Baz")
```

No argument-by-argument diff annotation here (no "argument #1 differs:
expected X, got Y") — deliberately simpler than the unexpected-call path,
because listing the actual call verbatim is enough for a person to spot a
typo or wrong value themselves, and adding a diff would imply a confidence
about *why* it differs that a plain method-name correlation hasn't earned.

### Symmetric extension, tracked but explicitly deferred: fact-based context on the unexpected-call path too

The unexpected-call diagnostic currently only shows *configured* candidates
(the closest expectation, via the similarity heuristic). It doesn't yet
show *other actual calls* to that same method that already happened and
were already successfully resolved earlier in the test — e.g. an
`allows('bar')->with('baz')` that already fired once, followed later by an
unmatched `bar('qux')`. Showing "`bar()` was already called successfully
with: `bar('baz')`" alongside the configured-candidate guess would be the
same category of addition as the `verify()` correlation above: a plain
fact pulled from `DoubleState`'s call log, not a similarity judgment, so it
carries the same "stated as fact, not speculation" guarantee.

This is genuinely the same mechanism in the opposite direction — Engine
would already be doing "correlate calls by method name" for the `verify()`
path once M3 lands, and extending that same correlation to the
unexpected-call diagnostic is a small, low-risk addition once it exists,
rather than a second mechanism to design from scratch. **Deliberately not
bundled into the M3 scope above, to avoid compounding two diagnostic
changes into one milestone** — but it belongs in the same "fact-based
correlation" family and should be picked up as a fast-follow once the
`verify()` side has shipped and proven out, not lost as a passing idea.
Worth revisiting explicitly at the start of whatever milestone follows M3.

## Exceptions and PHPUnit integration

### Core: framework-agnostic

No separate Diagnostic data class per exception (see "Module boundaries"
above) — each concrete exception holds its own fields directly and is its
own Diagnostic:

```php
abstract class TestDoubleException extends \RuntimeException implements Diagnostic
{
    public function getDiagnostic(): Diagnostic { return $this; }
}

class UnexpectedCallException extends TestDoubleException
{
    public function __construct(
        public readonly string $label,
        public readonly string $method,
        public readonly string $argumentsDescription,
        public readonly bool $fabricated = false,
    ) {
        parent::__construct($this->render());
    }

    private function render(): string { /* ... */ }
}
```

`getMessage()` gives full human prose with zero setup in any test runner.
`getDiagnostic()` gives structured access for anything that wants it — today
that's just `$this`, but the accessor stays so callers don't have to know
that.

**Every concrete exception is deliberately non-`final`** (only
`TestDoubleException` itself is `abstract`, and leaf `PHPUnitXxxException`
variants below are `final`) specifically so the PHPUnit split below has
something to extend. Getting this wrong the other way — leaving the base
exceptions `final` — would silently strand M5 until someone noticed the
subclass couldn't be written.

### PHPUnit integration — built in, `require-dev` only

Originally planned as a separate package/repo; changed to built-in, same
repo, same release. `phpunit/phpunit` goes in `require-dev`, constrained to
`^11.0 || ^12.0` — needed to develop and typecheck the integration classes,
never pulled in transitively for a consumer who isn't using PHPUnit. Both
majors are supported deliberately, not just whatever's newest: this
library's own PHP floor is 8.3, and PHPUnit 11 (PHP ^8.2) is still a real,
currently-supported choice for a consumer on that floor who hasn't moved to
PHPUnit 12 (PHP ^8.3) yet — there's no reason to narrow their options when
the two AssertionFailedError/SelfDescribing surface below is identical
between them. Proven, not assumed: the CI matrix crosses every supported PHP
version with both PHPUnit majors (`phpunit/phpunit` pinned explicitly per
job, not left to `composer update`'s latest-allowed default), since neither
PHPUnit major declares an upper PHP-version bound and there was no technical
reason to test only one PHPUnit version per PHP version.

**PHPUnit only counts a thrown exception as a *failure* (not a different
bucket, "error") if it extends `PHPUnit\Framework\AssertionFailedError`.**
This turned out to be in direct tension with an earlier draft of this
section, which sketched `PHPUnitUnexpectedCallException extends
UnexpectedCallException` so the PHPUnit variant would stay catchable as the
plain one. PHP has no multiple inheritance — a class cannot extend both
`UnexpectedCallException` (which extends `TestDoubleException` /
`RuntimeException`) and PHPUnit's `AssertionFailedError` at once. **Resolved
in favor of `AssertionFailedError`:** correct failure/error bucketing is the
entire reason this integration exists, so it wins over preserving `instanceof
UnexpectedCallException` under PHPUnit. Concretely, this means the three
exceptions that represent the double actually misbehaving *during* a
test — the ones `verify()`/`ProxyBehavior` can throw mid-test, as opposed to
setup-time misconfiguration — each got a PHPUnit-specific sibling under
`JMac\Testing\Integrations\PHPUnit\*`:

```php
final class PHPUnitUnexpectedCallException extends \PHPUnit\Framework\AssertionFailedError
    implements \JMac\Testing\Diagnostics\Diagnostic
{
    public function __construct(
        public readonly string $label,
        public readonly string $method,
        public readonly string $argumentsDescription,
        public readonly bool $fabricated = false,
    ) {
        // Same prose as the plain exception, without extending it — see
        // UnexpectedCallException::renderMessage(), a public static method
        // that both classes call so the message text has exactly one
        // source of truth.
        parent::__construct(UnexpectedCallException::renderMessage(
            $label, $method, $argumentsDescription, $fabricated,
        ));
    }

    public function getDiagnostic(): Diagnostic { return $this; }
}
```

`ExpectationCallLimitExceededException` and `UnsatisfiedExpectationException`
get the identical treatment (`PHPUnitExpectationCallLimitExceededException`,
`PHPUnitUnsatisfiedExpectationException`), each calling its plain
counterpart's `renderMessage()`. Setup-time exceptions
(`UnknownMethodException`, `ModeConfigurationException`,
`InvalidDoubleTargetException`, `PassthruAutoInstantiationException`,
`ReservedNameCollisionException`) deliberately do **not** get a PHPUnit
sibling — a misconfigured double is legitimately a PHPUnit "error," not a
test "failure," so the default `RuntimeException`/`LogicException`
bucketing they already get is correct as-is.

**`SelfDescribing` needs no separate `implements`:** `AssertionFailedError`
already implements it and supplies `toString(): string { return
$this->getMessage(); }` itself, non-`final`, so extending
`AssertionFailedError` is sufficient on its own.

**`getComparisonFailure(): ?ComparisonFailure` diff integration, dropped
from scope, not deferred:** this section's original sketch assumed any
exception exposing that method would get PHPUnit's automatic diff output.
Checked against real PHPUnit source (11.x and 12.x): the diff renderer
(`Util\ThrowableToStringMapper`) only calls `getComparisonFailure()` after an
explicit `instanceof \PHPUnit\Framework\ExpectationFailedException` check —
a `final` class. There's no way to become one by subclassing, and no other
generic hook. Duck-typing a same-named method onto our own exceptions would
be dead code: it would never be called by anything, since nothing checks
for the method, only for that exact final type. If diffing is wanted later,
the honest path is constructing a real `ExpectationFailedException`
(composition, not inheritance) and deciding what that does to the
catchable-type story above — a fresh design question, not "wire up the
method that's already there."

`ProxyBehavior` and `TestDouble::verify()` pick which exception to throw via
`Engine\ExceptionFactory`, an `@internal` class whose only job is the
`class_exists(\PHPUnit\Framework\TestCase::class)` branch — this keeps that
check in exactly one place and keeps `ProxyBehavior`/`TestDouble` themselves
unaware PHPUnit exists at all.

**Correctness detail specific to this pattern, easy to get almost right:**
extending `AssertionFailedError` (or referencing any `Integrations\PHPUnit`
class) resolves the parent the moment the file is *autoloaded* — not when
the exception is instantiated. The whole "optional integration" promise
depends on these classes never being autoloaded unless `AssertionFailedError`
already exists. A `class_exists()`-guarded `new` (what `ExceptionFactory`
does) is safe; a registry/factory pattern that references the class name
unconditionally (e.g. an `extends`/`implements` outside the guarded branch)
breaks the promise silently. Mitigations in place:

- A comment directly on `ExceptionFactory` and on each `Integrations\PHPUnit`
  class explaining why it must only ever be referenced inside the
  `class_exists()`-guarded branch.
- Existing tests exercise the guarded-true branch for real (this repo's own
  suite always has PHPUnit installed, so `TestDoubleTest`/`LooseModeTest`'s
  runtime assertions were updated to expect the `PHPUnitXxxException`
  variants, not the plain ones — proving the switch fires, not just that the
  plain classes work in isolation).

**Still open, not yet built:** a CI job that runs the test suite with
PHPUnit's classes genuinely unavailable (a separate `composer.json` with the
dev dependency stripped, run as its own matrix entry), proving the
guarded-false branch instead of just reasoning about it. The `phpunit-11-compat`
job added alongside this work proves the *version* half of the "optional
integration" promise; it does not prove the *absent* half.

**Future, additive only:** a PHPUnit extension that auto-verifies doubles
created during a test at teardown, removing the need for a manual
`TestDouble::verify($double)` call for PHPUnit users specifically. Manual
`verify()` remains the baseline contract for every other test runner.

## Known scaffold-era limitations to design around, not just inherit

- `ClassGenerator` uses `eval()` (same technique Mockery/Prophecy use) and
  currently would regenerate a class on every `TestDouble::for()` call — a
  real implementation should cache generated classes per target type.
- Magic methods (`__toString`, `__invoke`, `ArrayAccess`, etc.) need
  explicit, deliberate handling, not silent non-support.
- `final` classes cannot be doubled via `extends` — detect at setup time
  with a clear error.
- Static analysis (PHPStan/Psalm) will have real blind spots around the
  `eval()`-generated classes and heavy `Reflection` use — plan for stub
  files or an explicit baseline at that boundary.
- `ClassGenerator`'s type-signature reconstruction (unions, intersections,
  enum defaults, variadics, by-ref params) is the most version-sensitive
  code in the library and needs its own dedicated compatibility test suite
  across the full supported PHP version range — not just "tests pass on
  latest PHP."

## Roadmap

1. **M0 — Bootstrap.** composer.json, PSR-4 autoload, PHPUnit for the
   library's own tests, CI matrix (PHP 8.3, 8.4, and 8.5, plus the
   PHPUnit-unavailable job and an allowed-to-fail PHP 8.6 job once builds
   exist), MIT license, `CONTRIBUTING.md` including the no-alias policy.
2. **M1 — Core engine.** `ClassGenerator`, `ProxyBehavior`, `DoubleState`,
   `MethodExpectation`, `TestDouble` facade. Strict mode only — defer Loose
   fabrication, it's the riskiest piece. Include the reserved-name collision
   check from day one, not as a later hardening pass.
3. **M2 — Matcher contract.** `Matcher` interface, `EqualsMatcher`,
   `Argument::any()/type()/satisfies()`.
4. **M3 — Diagnostics pipeline.** `Diagnostic`, `DiagnosticRenderer`,
   `PlainTextRenderer`, golden-file test harness. Top priority within this
   milestone: `UnsatisfiedExpectation`'s call-correlation feature (see
   "Correlating unsatisfied expectations with actual observed calls") — it
   directly addresses the most common real-world failure mode and
   shouldn't be deferred to a later polish pass. **Post-M4 revision:** the
   original one-`Diagnostic`-class-per-exception pairing from this milestone
   was collapsed — see "Module boundaries" and "Exceptions and PHPUnit
   integration". `DiagnosticRenderer`/`PlainTextRenderer` no longer exist;
   each exception renders itself.
5. **M4 — Modes.** Loose (safe-default table, recursive fabrication, depth
   cap, provenance tagging) and Passthru, built against the stable Strict
   baseline and diagnostics pipeline. Includes the shared
   safe-default-by-return-type resolver used by both Loose's fallback and
   any expectation missing an explicit `->returns()`.
6. **M5 — PHPUnit integration**, in-repo. Done: the three mid-test
   exceptions (`UnexpectedCallException`, `ExpectationCallLimitExceededException`,
   `UnsatisfiedExpectationException`) each gained an `Integrations\PHPUnit`
   sibling extending `AssertionFailedError`, picked at throw time by
   `Engine\ExceptionFactory`'s `class_exists()` check; PHPUnit 11 and 12 both
   supported and proven in CI. **Revised, not built as originally sketched:**
   the siblings extend `AssertionFailedError` rather than the plain
   exception (PHP's single inheritance forced a choice — see "PHPUnit
   integration" for why), and the `ComparisonFailure`/diff hook was dropped
   entirely rather than wired up, since PHPUnit's real diff renderer only
   fires for its own `final` `ExpectationFailedException`, not a
   duck-typed method. Still open: the auto-verify PHPUnit extension (always
   framed as "future, additive" — not started), and the guarded-false
   ("PHPUnit genuinely absent") CI job.
7. **M6 — Docs and first release.** Cookbook-style task docs, the Mockery/
   PHPUnit rosetta-stone migration table, the two contributor walkthroughs
   ("add a matcher," "improve a message"), freeze `Matcher` and `Diagnostic`
   shapes, tag `1.0.0`.

Ship `0.x` throughout M1–M5 specifically so early shape mistakes in
`Matcher` or `Diagnostic` aren't breaking changes yet.
