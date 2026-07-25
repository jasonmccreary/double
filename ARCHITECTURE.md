# Architecture

This document captures the design decisions behind this library — a modern,
human-friendly PHP mocking library built as an alternative to Mockery. It's
written for whoever picks up implementation next (including an AI coding
agent), so it includes not just *what* was decided but *why*, and flags what
is still explicitly open.

**This is a living record of reasoning, not a spec.** It evolves alongside
the code — when the two disagree, the code is current and this document is
stale, not the other way around; update it to match rather than treating it
as something the code must conform to. The same goes for any claim in here
about a third-party library's behavior (Mockery, RSpec, PHPUnit, etc.):
these were true when checked against that library's real source at the
time, not guaranteed evergreen — re-verify before relying on one, don't
cite this document as proof on its own.

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
| `shouldReceive('foo')->once()->andReturn($x)` | `expects($this->once())->method('foo')->willReturn($x)` | `expects('foo')->returns($x)` (exactly-once is expects()'s own default — see below) |
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
$repo->expects('find')->with(123)->returns($book);   // exactly once by default
$repo->allows('save')->returns(true);

// sequential returns on repeated identical calls — RSpec's and_return(1, 2, 3)
// and Mockery's andReturn(1, 2, 3): first call gets $first, second gets
// $second, third+ holds at $second. This is what resolves the ordering
// question below — sequencing is a property of ONE expectation, never
// modeled as multiple competing expectations.
$repo->allows('find')->with(1)->returns($first, $second);

$repo->allows('find')->with(999)->throws(new NotFoundException());

// counts — one overloaded verb, not once()/twice()/atMost()/between() as
// separate words (see "Overloading times() instead of adding count verbs"
// below for why once()/twice() were dropped and Mockery's three-verb
// atLeast()/atMost()/between() collapse into this one).
$repo->expects('save')->times(3);              // exactly 3
$repo->expects('save')->times(1, 3);           // between 1 and 3
$repo->expects('save')->atLeastOnce();
$repo->expects('save')->times(minimum: 2);     // at least 2 (no upper bound)
$repo->allows('save')->times(maximum: 5);      // at most 5 (no lower bound)
$repo->allows('save')->never();                // no separate "reject" verb needed

// partial mock — whole-double only, not a per-expectation modifier (see
// "Passthru is whole-double only" below for why Mockery's per-expectation
// passthru() doesn't have an equivalent here).
$fullDouble = TestDouble::for(Logger::class)->passthru($realLogger);

// one specific method delegating to a real object, on an otherwise
// Strict/Loose double — a resolves() closure, not a separate verb.
$repo->allows('calculateTax')->resolves(fn (...$args) => $realGateway->calculateTax(...$args));

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

Starting matcher set: `Argument::any()`, `Argument::type($type)` (a
class/interface name, matched via `instanceof`, or a PHP builtin type name
like `'int'`, matched via the corresponding `is_*()` — mirrors
`Mockery::type()`, confirmed against Mockery's own source, which accepts
both the same way), `Argument::satisfies($predicate)`. Deliberately minimal
for v1 — add more once real usage shows what's actually needed, rather than
porting RSpec/Mockery's full matcher catalog speculatively.

### Overloading times() instead of adding count verbs

Mockery expresses "no more than N" and "between N and M" as two more verbs,
`atMost()` and `between()` (confirmed against Mockery's own source:
`Expectation::between($min, $max)` is literally
`$this->atLeast()->times($min)->atMost()->times($max)` — three verbs
standing in for one range, not a single primitive). Porting that directly
would grow the count-verb list from `once()`/`twice()`/`times()`/
`atLeastOnce()`/`never()` to eight-plus words for what's fundamentally one
concept: a lower bound and an upper bound.

**Decision: one overloaded `times()`, not new verbs.** `MethodExpectation`
already stored an arbitrary `$minimumCalls`/`$maximumCalls` pair internally
— `times(3)`, `never()`, and `atLeastOnce()` were always just three fixed
points on the same range, so the range-setting primitive already existed;
only the verbs to reach every point on it didn't.

```php
$repo->expects('save')->times(3);                  // exactly 3        min=3, max=3
$repo->expects('save')->times(1, 3);                // between 1 and 3 min=1, max=3
$repo->expects('save')->times(minimum: 2);          // at least 2      min=2, max=∞
$repo->allows('save')->times(maximum: 5);           // at most 5       min=0, max=5
$repo->expects('save')->times(minimum: 1, maximum: 3);  // same as times(1, 3)
```

`$count` (the positional slot) is the exact value when given alone, and the
lower bound when paired with `$maximum` — which is why `times(1, 3)` and
`times(minimum: 1, maximum: 3)` resolve identically. Supplying both `$count`
and `$minimum` is rejected (`InvalidArgumentException`) as ambiguous — two
values both trying to set the same lower bound — and so is `times()` with
nothing at all, and a `$minimum` greater than `$maximum`.

**`once()` and `twice()` are dropped, not just left alongside `times()`.**
Both were pure aliases for `times(1)`/`times(2)` — the "no aliases" policy
already argued against keeping them once `times()` reads just as plainly.
`once()` was additionally redundant for its most common use (`expects()`
already defaults to exactly-once), which the old rosetta-table example
above used to demonstrate unintentionally: `expects('foo')->returns($x)`
and `expects('foo')->returns($x)->once()` were always the same expectation.

**`never()` stays a real, separate verb — not folded into `times(0)` at the
call site.** Internally `never()` delegates to `times(0)` (one bounds-setting
code path, not two), but `times(0)` reads as an awkward, easy-to-misread
call for a person to actually write — `never()` is the one place this audit
decided the dedicated word earns its keep over the overloaded primitive
underneath it.

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

`Argument::remaining()` is the next post-v1 addition — Mockery's equivalent
(`andAnyOtherArgs()`/`withAndOthers()`, confirmed against Mockery's source)
needed a dedicated matcher class (`AndAnyOtherArgs`, checked specially in
`Expectation::matchArgs()`) because `with()` otherwise requires an exact
argument-count match. Not a second sense of `any()`: `any()`'s meaning
(matches exactly one position) would become ambiguous if it also meant
"consume every remaining position" when trailing — so this needed its own
name, not an overload of an existing one. Went through `rest()`/`tail()`/
`etc()` before landing on `remaining()` as the least likely to make someone
pause and guess.

```php
$repo->allows('combine')->with('-', Argument::remaining())->returns('a-b-c');

$repo->combine('-', 'a');           // matches
$repo->combine('-', 'a', 'b', 'c'); // also matches — any further args are unconstrained
```

Only valid as the last argument to `with()` — `with(Argument::remaining(), 2)`
doesn't mean anything coherent, so `with()` throws rather than silently
picking an interpretation. `MethodExpectation::matchesArguments()` is what
actually special-cases it (drop the trailing marker, require `count($arguments)
>= count($remainingConstraints)` instead of an exact match); `RemainingMatcher`
itself is a plain `Matcher` like every other one, so `with()` needs no special
casing to accept it. `ReceivedAssertion::with()` gets this for free, with no
code of its own, since it already delegates straight through to the same
`MethodExpectation::with()`.

`Argument::none()` is the next post-v1 addition, prompted by comparing
against Mockery's `withNoArgs()`. That comparison turned up that bare
`->with()` — no arguments at all — already asserts a zero-argument call:
`$argumentConstraints` becomes `[]` rather than staying `null`, so the
existing `count($constraints) !== count($arguments)` check in
`matchesArguments()` already requires exactly zero real arguments. Correct,
but entirely an emergent side effect of the general count-match rule, not a
designed feature — nothing distinguishes it from "forgot to pass args" at
the call site, and refactoring `->with($a, $b)` down to `->with()` mid-edit
silently flips a specific-arguments assertion into a zero-arguments
assertion, the opposite of what a stripped-down `with()` implies to a
reader.

Rejected a top-level `withNoArguments()`: not a real-world-common-enough
case to earn a new domain verb the way `remaining()` was, and it would
still leave the ambiguous bare-`with()` behavior sitting there unexplained.
Also considered a class constant, `Argument::None`, for a lighter call site
than a method call — rejected on a hard technical constraint, not taste:
PHP forbids `new` in class constant initializers (confirmed directly
against this project's PHP 8.4: `New expressions are not supported in this
context`), so `Argument::None` can't hold an actual `Matcher` instance,
only a sentinel value that `with()` would then need to special-case
*before* its existing bare-literal-to-`EqualsMatcher` wrap step — more code
than the method form, and the one constant on a facade where every other
verb is a method.

**Decision: `Argument::none()`**, returning `NoneMatcher`, must be the only
argument passed to `with()` — `with(Argument::none(), 2)` doesn't mean
anything coherent, same reasoning `remaining()`'s last-position-only rule
already established, so `with()` throws rather than guess.
`MethodExpectation::matchesArguments()` special-cases it the same way it
special-cases `RemainingMatcher`: short-circuits to `$arguments === []`
before the positional count comparison runs, since a single `NoneMatcher`
constraint isn't a real position to compare a real argument against. Bare
`->with()` keeps matching zero arguments exactly as before — `none()`
doesn't replace that behavior, it gives the same assertion a name that says
what it means at the call site instead of relying on a reader already
knowing the count-match rule.

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

**`TestDouble::for($realInstance)` — a real instance in place of a class
name — remembers that instance for a later `->passthru()` with no
argument**, so the class name doesn't need to be spelled out twice when you
already have the instance in hand:

```php
$double = TestDouble::for($realBook)->passthru();
// instead of: TestDouble::for(Book::class)->passthru($realBook)
```

Deliberately *not* auto-wiring — this only accepts an already-built
instance, never a class name plus constructor arguments for the library to
construct on your behalf. Considered and rejected: a constructor-args array
(mirroring `Mockery::mock(Class::class, [$args])`) only covers positional
constructor arguments, whereas handing over an object already-built covers
every construction strategy there is — a factory method, a builder, a
dependency the caller mocked separately, an instance reused from elsewhere
in the test — with no special-casing needed, and it gets real
type-checking on the construction itself instead of an untyped args array.
Only valid for a single target: which real instance a later `->passthru()`
should fall back to becomes ambiguous the moment more than one target is
involved (`TestDouble::for($x, SomeInterface::class)`), so that combination
is rejected rather than guessed at.

Because the derived target is the instance's own concrete class, not an
interface, the resulting double still satisfies every interface that class
implements — confirmed directly (not assumed): a class generated as
`class Generated extends RealLogger` is `instanceof LoggerInterface` purely
from PHP's own transitive interface inheritance through `extends`, nothing
this library does specially. So a double built this way can still be bound
into an IoC/DI container wherever the interface is expected.

**Passthru is whole-double only — deliberately not a per-expectation
modifier the way Mockery's `passthru()` is.** Considered and rejected, for
two independent reasons:

- **It isn't portable the way it first looks.** Confirmed against Mockery's
  own source: Mockery's per-expectation `passthru()` doesn't use a separate
  real instance at all — `Expectation::verifyCall()` calls
  `$this->_mock->mockery_callSubjectMethod(...)`, which does
  `call_user_func_array($this->_mockery_parentClass . '::' . $name, $args)`,
  i.e. a `parent::` call on the mock object itself. That only produces
  correct results if the mock's own internal state was properly
  initialized — which is why Mockery explicitly refuses to allow `passthru()`
  on a mock that isn't based on a real, loaded class, and why it's only
  really safe on a mock built with real constructor arguments
  (`Mockery::mock(Class::class, [$args])`). This library's generated doubles
  have no equivalent opt-in: `ClassGenerator`'s classes always skip the real
  constructor (`__td_instantiate()` calls `newInstanceWithoutConstructor()`),
  with no path to run it instead. A `parent::`-style call here would always
  be operating on an object whose real properties were never set — so the
  mechanism Mockery's version actually relies on isn't available at all,
  not just "not yet built."
- **A separate real instance would be required either way, and `resolves()`
  already covers that with no new verb.** Given `parent::` isn't viable, a
  per-expectation passthru would need the same kind of real instance the
  whole-double version already uses (auto-instantiated or supplied) — real
  plumbing (threading the declaring class into `MethodExpectation`, a new
  auto-instantiate-or-require-explicit design fork). But that instance still
  has to come from somewhere, usually built by hand for the occasion, and at
  that point `resolves()` — already shipped, no new concept — does the same
  job in one line:
  `$repo->allows('calculateTax')->resolves(fn (...$args) => $realGateway->calculateTax(...$args))`.
  Delegating to a real object for one specific call isn't a distinct
  concept needing its own verb; it's one use of "compute the return value
  however you want," which is exactly what `resolves()` already is.

## Class surface area — reserved names on the generated double (resolved)

Because configuration verbs live directly on the double itself (fluent,
Eloquent-style chaining, not a separate facade), six real method names are
reserved on every generated double: `expects`, `allows`, `strict`,
`passthru`, `received`, `verify`. A target type that happens to declare a
real method with one of these names collides.

**Decision: keep all six as real instance methods. Do not try to engineer
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

**The risk is real for all six, not just `strict`.** `allows()`
specifically is probably the single most likely collision in the entire
verb set: authorization/policy interfaces routinely declare a real
`allows($ability, ...$args): bool` method (Laravel's own `Gate` contract
uses this exact verb), so doubling an `AuthorizerInterface` hits this
immediately, in a mainstream testing scenario, not an edge case. `received()`
is plausible in messaging/delivery domains. `expects()` is the least likely
of the three but not zero (HTTP content-negotiation, e.g. Laravel's own
`Request::expectsJson()`). `verify()` is plausible wherever a target
implements its own validation contract (e.g. a signature/checksum
`Verifiable` interface).

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
$reserved = ['expects', 'allows', 'strict', 'passthru', 'received', 'verify'];
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
(`TestDouble::for()`/`$double->verify()`), so surfacing an internal
module name (`Engine`) in its own fully-qualified name would leak an
implementation detail into the most-seen part of the public API. This is a
namespace/discoverability change only — `TestDouble` still delegates to
Engine's internals exactly as before, and those internals stay `@internal`
under `JMac\Testing\Engine\*`. Every other module depends only on what's
strictly beneath it:

- **Diagnostics** — zero dependencies on the rest of the library. Originally
  shrunk down to just the `Diagnostic` marker interface and
  `UnsatisfiedExpectation`; now also the shared home for rendering logic more
  than one other module needs — `ValueFormatter`, `ArgumentFormatter`,
  `Pluralizer`, and the `SelfDiagnosing` trait (`getDiagnostic(): Diagnostic
  { return $this; }`, needed by every `Integrations\PHPUnit\PHPUnitXxxException`
  since they can't inherit it from `TestDoubleException`). Grown into this
  role specifically so that logic has exactly one implementation, not one
  hand-duplicated per module that happened to need it. `ValueFormatter` and
  `ArgumentFormatter` stay two classes, not one, even though they're
  co-located now: unlike the M3-era split (which crossed a module boundary,
  the actual reason for keeping them apart back then), there's no drift risk
  today — `ArgumentFormatter` only ever composes `ValueFormatter`, never
  duplicates it — so the split is just "single value vs. a whole argument
  list," a distinction worth keeping legible as two small files rather than
  collapsing for its own sake.
- **Matching** — depends only on Diagnostics (for `ValueFormatter`, inside
  `EqualsMatcher`/`PredicateMatcher`'s `describe()`/`explainMismatch()`).
  Nothing else.
- **Exceptions** — depends only on Diagnostics.
- **Integrations\PHPUnit** — a fourth module in this same sense: depends on
  Exceptions (for each exception's `*Fields` trait — see "PHPUnit
  integration"), on Diagnostics (for `Diagnostic` and `SelfDiagnosing`), and
  conditionally on PHPUnit's own classes. Nothing else in the codebase
  depends on it — Engine picks between "plain exception" and "PHPUnit
  exception" via a `class_exists` check without needing to know anything
  about `SelfDescribing` or `ComparisonFailure`.

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

**Resolved: `Matcher` is a frozen, semver-guaranteed public contract, not
just an internal detail that happens to be reachable.** Someone implementing
`Matcher` themselves — a reusable, named domain matcher (`IsValidIsbn`,
`MatchesJsonSchema`) rather than an anonymous `Argument::satisfies()`
closure repeated at every call site — can rely on the three-method shape
not changing outside a major version. This is why `explainMismatch()` was
already kept a plain `?string` instead of being widened for richer data
(see above): any future capability needs an additive, optional interface
(`ExplainsWithDetail extends Matcher`), never a change to the base three
methods. `Argument` itself is explicitly outside this freeze — it's a
static facade consumers call, not an interface they implement, so it can
keep growing/tightening incrementally without a stability promise attached.

**A second, distinct decision, split out from the above rather than folded
into it: are each `TestDoubleException` subclass's own public readonly
fields (`UnexpectedCallException::$method`, `UnsatisfiedExpectation::
$otherObservedCalls`, etc.) also committed as stable, semver-guaranteed
API?** This is not the same question as `Diagnostic`'s own shape (already
resolved above as "internal detail, coupled to the exception hierarchy" —
and moot besides, since `Diagnostic` is a zero-method marker interface with
nothing in it to freeze). The real public surface a consumer catches and
inspects programmatically lives on each concrete exception class, one field
set per exception, not on the marker interface.

**Resolved: yes.** "Core: framework-agnostic" above already states
`getDiagnostic()` gives structured access "for anything that wants it" —
that promise only means something if the fields it exposes are safe to
depend on, not silently reshaped in a minor release. Freezing them costs
little beyond what's already true in practice: every field on every
concrete exception is already `public readonly`, set once in the
constructor, and none has changed shape since `M3`/`M4` landed. The
practical follow-through is a per-class audit at `M6` (confirm each
exception's current field set is one the project is actually willing to
commit to, not an accidental leftover from an earlier draft) rather than a
design decision still to be made — narrower work than the open question
above implied.

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

### Symmetric extension: fact-based context on the unexpected-call path too (resolved)

The unexpected-call diagnostic used to only show *configured* candidates
(the closest expectation, via the similarity heuristic). It didn't show
*other actual calls* to that same method that already happened earlier in
the test — e.g. an `allows('find')->with(123)` that already fired once,
followed later by an unmatched `find(456)`. This was tracked here as
deliberately deferred (not bundled into M3, "to avoid compounding two
diagnostic changes into one milestone") and picked up as the fast-follow
this note always said it should be, once the `verify()` side had shipped
and proven out.

**Built as the same mechanism in the opposite direction, exactly as
predicted above.** `UnexpectedCallFields` gained an `otherObservedCalls`
field (`list<string>`, same shape as `UnsatisfiedExpectation`'s own field —
a plain fact pulled from `DoubleState::callsFor()`, matched or not, never a
similarity guess). `ProxyBehavior::handleUnmatchedCall()` supplies it by
slicing off the last entry of `callsFor($method)` — `recordCall()` already
ran before matching, so that last entry is always this same failing call,
not a genuinely prior one.

**Also surfaced and fixed in the process: the existing `verify()`
correlation had no cap.** `TestDouble::verifyState()` mapped every call in
`DoubleState::callsFor()` straight into `otherObservedCalls` with nothing
bounding it, and the old `UnsatisfiedExpectationFields::renderCorrelation()`
`implode()`d all of them onto one line — a method called many times
legitimately (once per loop iteration with a different id, say) would
already have turned a one-line diagnostic into a wall of text, and the new
unexpected-call correlation would have inherited the identical problem if
built the same unbounded way. **Decision: cap at 3, collapse anything past
that to "and N more"** rather than either an unbounded list or Mockery-style
full suppression once there's "too much" to show — a long list is verbose,
not wrong, so it doesn't need `DidYouMean`'s "a wrong guess is worse than
none" treatment, just a ceiling on how verbose. Both correlation features
share this cap through one implementation, `Diagnostics\CallListFormatter`,
rather than each hand-rolling its own truncation. The full, uncapped list
still lives on the exception's own public field regardless — only the
rendered prose is capped, since these fields are frozen public API (see
"Matcher" above) and silently truncating the data itself would be a
surprising, lossy thing for that contract to do.

**Refined: the cap isn't a flat "show the first 3, always."** At exactly 3
calls, all 3 are shown and nothing is truncated — there's no "more" to
speak of. At 4, only 2 are shown, not 3: `` `find(1)`, `find(2)`, `find(3)`,
and 1 more `` was the first cut, but showing 3 of 4 and then saying "and 1
more" reads as arbitrary — if there's room to show a fourth, why withhold
exactly one? Dropping to 2 shown once truncation starts at all (`` `find(1)`,
`find(2)`, and 2 more ``) reads unambiguously as a deliberate summary
instead. Covered directly by a dedicated `CallListFormatterTest` (the
3-vs-4 boundary specifically, plus a many-calls case), not just indirectly
through the golden-file exception tests.

**Revised during review: the correlation reads as its own paragraph, not an
appended sentence, and both exception types now share identical phrasing.**
`Diagnostics\CallListFormatter::renderCorrelationParagraph()` renders "The
following calls to `` `%s` `` were made during this test: ..." as a
`"\n\n"`-prefixed block ending in its own `"\n"` — used verbatim by both
`UnexpectedCallFields` and `UnsatisfiedExpectationFields`, rather than the
two drifting into their own phrasing the way an earlier draft of this
change had them do (`` `find` was also called elsewhere in this test: ...
`` vs. `` `bar` was called elsewhere in this test, just with different
arguments: ... ``). Every individual call is backtick-wrapped
(`` `find(123)` ``, not bare `find(123)`), matching how every other list of
code-like tokens in this codebase's messages already reads — see
`ReservedNameCollisionException::forCollisions()`'s "declares method(s)
`` `expects` `` and `` `verify` ``" for the existing precedent this was
brought in line with, not a new style invented for this feature.

**Also revisited: the unexpected-call message's old "Add:
`$bookRepository->allows('count')->returns(...);`" suggestion.** Dropped
initially on the reasoning that it was never genuinely copy-pasteable — it
guesses on three separate axes at once (the variable name, derived from the
double's *label* rather than the test's actual code; `...` as a return-value
placeholder that isn't real code; and `allows()` asserted as the verb with
no basis, when `expects()` is just as plausible and arguably more likely for
someone using Strict mode in the first place). That's speculation dressed as
fact, which cuts against this document's own stated principle for these
messages. But dropping it unconditionally went too far the other way: with
*no* correlation data available (nothing else was ever observed for that
method), the message was left with no next step at all beyond "this wasn't
configured." **Resolved: the suggestion only appears when there's nothing
stronger to offer — i.e. exactly when `otherObservedCalls` is empty.** Once
real correlation data exists, it's shown instead of the guess, never
alongside it. Also reworded from `Add: $x->y();` to `` For example:
`$x->y()`. `` — matching the phrasing and backtick-wrapped-whole-snippet
style `PassthruAutoInstantiationException` and
`FabricationLimitExceededFields` already use for the same kind of
illustrative-not-literal code suggestion, rather than inventing a third
style for it.

**Also extracted while making this change, since it was about to be
hand-duplicated a second time:** `TestDoubleException::
appendFabricatedNote()` joins a rendered message with `fabricatedNote()`'s
own `"\n\n"`-prefixed text, only trimming the message's trailing newline
when there's actually a note to append — needed because a correlation
paragraph now deliberately ends in its own `"\n"`, and naive concatenation
against `fabricatedNote()`'s leading `"\n\n"` would leave a stray blank
line on a double that's both fabricated and has correlation data.

**Built:** `Diagnostics\CallListFormatter` (`describe()` plus the new
`renderCorrelationParagraph()`), `TestDoubleException::
appendFabricatedNote()`, `UnexpectedCallFields::$otherObservedCalls` (+ its
PHPUnit sibling via the same shared trait) and its conditional `For
example:` suggestion, `ProxyBehavior::handleUnmatchedCall()`'s
slice-off-the-last-entry wiring, and the same cap and paragraph structure
now applied to `UnsatisfiedExpectationFields`'s pre-existing correlation.
Covered in `ExceptionMessagesTest` (single-call and capped-multi-call golden
fixtures for both exception types), `PHPUnitExceptionsTest` (parity between
the plain and PHPUnit variants with the field populated), and
`TestDoubleTest` (end-to-end through `ProxyBehavior`, proving the failing
call itself is excluded from its own correlation list).

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

**Resolved: a CI job now runs with PHPUnit's classes genuinely unavailable,**
proving the guarded-false branch instead of just reasoning about it. Not the
ordinary test suite, deliberately — `vendor/bin/phpunit` is itself PHPUnit,
so "run the test suite with PHPUnit absent" is a contradiction; the ordinary
matrix jobs prove the *version* half of the "optional integration" promise
(PHPUnit 11 and 12, across every supported PHP version), never the *absent*
half. Instead, a `phpunit-absent` job runs `composer install --no-dev`
(which drops `phpunit/phpunit` from `vendor/` entirely, and disables
`autoload-dev` as a side effect — `tests/Support`'s fixtures go with it) and
executes a standalone, deliberately self-contained script,
`tests/smoke/without-phpunit.php`, with its own inline fixture interface
rather than depending on anything under `tests/`. Plain assertions with a
non-zero exit on failure, since PHPUnit itself isn't installed when this
runs to assert anything with. Confirmed to actually catch a regression, not
just pass by construction: temporarily short-circuiting `ExceptionFactory::
phpUnitIsAvailable()` to always return `true` (simulating a naive
implementation that references the PHPUnit sibling classes unconditionally)
made the script fail with `Class "PHPUnit\Framework\AssertionFailedError"
not found` on both the unexpected-call and unsatisfied-expectation checks,
exit code 1 — proof the check is load-bearing, not just green by default.

**Built, not an "Extension":** `Integrations\PHPUnit\VerifiesDoubles`, a
trait a PHPUnit user adds to a base TestCase, auto-verifies every double
created during a test via `#[Before]`/`#[After]` hooks — removing the need
for a manual `$double->verify()` call. Confirmed, by reading the installed
phpunit/phpunit source, that a PHPUnit "Extension" (the bootstrap/
event-subscriber mechanism) genuinely cannot do this: `Runner\Extension\Facade`
only exposes registering event subscribers, which is read-only observability
— only a `TestCase` lifecycle method running inside `runBare()`'s own
try/catch can actually fail a test. Manual `verify()` remains the baseline
contract for every other test runner, and for PHPUnit users who don't opt
into the trait.

## Known scaffold-era limitations to design around, not just inherit

- `ClassGenerator` uses `eval()` (same technique Mockery/Prophecy use) and
  currently would regenerate a class on every `TestDouble::for()` call — a
  real implementation should cache generated classes per target type.
- `final` classes cannot be doubled via `extends` — detect at setup time
  with a clear error.
- Static analysis of *this library's own* `src/` will have real blind spots
  around the `eval()`-generated classes and heavy `Reflection` use inside
  `ClassGenerator` — a tool can't type-check code it can't see until
  runtime, so this needs an explicit baseline or targeted `eval()`-site
  suppression before PHPStan/Psalm ever run in this repo's own CI. Left
  deliberately unaddressed for now (see "Static analysis: a sound
  TestDoubleInterface, not a mixin" — the *consumer*-facing half of the
  original static-analysis gap, `TestDouble::for()`'s return type, is
  resolved there).
- `ClassGenerator`'s type-signature reconstruction is the most
  version-sensitive code in the library — see "Type-signature reconstruction
  coverage audit" for what auditing this turned up and fixed.

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
   duck-typed method. Also done: `Integrations\PHPUnit\VerifiesDoubles`, the
   auto-verify trait — built as a `#[Before]`/`#[After]`-hooked trait, not a
   PHPUnit "Extension" (see "PHPUnit integration" for why an Extension
   can't actually fail a test). Also done: the guarded-false ("PHPUnit
   genuinely absent") CI job — see "PHPUnit integration" for the
   `tests/smoke/without-phpunit.php` script it runs.
7. **M6 — Docs and first release.** Cookbook-style task docs, the Mockery/
   PHPUnit rosetta-stone migration table, the two contributor walkthroughs
   ("add a matcher," "improve a message"), tag `1.0.0`. The two API-stability
   decisions this milestone used to gate on are both resolved now, not open
   design work — `Matcher`'s three-method shape and each `TestDoubleException`
   subclass's public field set are both committed as frozen, semver-guaranteed
   public API; see "Matcher" above. What's left of that thread by `M6` is a
   confirming audit of each exception's current fields, not a decision.

Ship `0.x` throughout M1–M5 specifically so early shape mistakes in
`Matcher` or `Diagnostic` aren't breaking changes yet.

## Call-order enforcement (resolved)

Mockery's `ordered()`/`globally()` — asserting that calls across one or more
expectations happen in a specific relative sequence — has no equivalent
here today, and unlike the other gaps found against Mockery (`atMost`/
`between`, exception sequencing, trailing argument matching), there was no
existing primitive this could be folded into; it's a genuinely new
capability. The concern originally flagged here — that it has to interact
carefully with the "last-registered-that-matches wins" rule this library
deliberately chose over Mockery's own first-registered-wins matching order
(see "Sensible defaults" and the `byDefault()` comparison) — turned out to
have a clean answer once checked against real prior art rather than assumed:

**Confirmed against Mockery's own source, not assumed:** `Expectation::
verifyCall()` calls `validateOrder()` *after* an expectation has already
been selected as the match for a call (`Mock::__call()` finds the
expectation first via the existing selection logic; ordering is checked
only once that selection has already happened). Order enforcement in
Mockery was never part of match selection — it's a check layered on top of
whichever expectation got picked. **Call order and match-selection order
don't need to interact at all, and this design keeps them fully separate**:
last-registered-that-matches-wins is untouched by any of what follows.

**Decision: the verb is `inOrder()`, not `ordered()`.** `ordered()` (Mockery
and RSpec's shared name) only reads correctly if the reader already knows
the convention it's borrowed from — the same trap this document's motivating
gripe #1 calls out in Mockery generally ("leads with a taxonomy that only
makes sense to someone who already understands test doubles"). `inOrder()`
reads as a complete English phrase with no prior knowledge required:
`expects('open')->inOrder()` = "expects open, in order." A deliberate
one-time exception to preferring single-word modifiers, not a precedent for
compounding names elsewhere — weighed and picked for its own sake, not
because a single word wasn't tried. Single-word alternatives considered and
rejected: `sequenced()` (collides with this document's own already-named
"sequential returns" feature — same word, unrelated concept, exactly the
kind of collision `satisfies()` was picked to avoid against `Matcher::
matches()`); `serially()` (no existing modifier uses an `-ly` shape, and
"serial" carries the wrong connotation); `consecutive()` (implies
back-to-back with nothing interleaved, which isn't the real semantics —
unrelated calls may legitimately happen in between); `queued()` (reads as
deferral/scheduling, not an order constraint, and brushes against
"sequential returns" again).

**Decision: scoped per-double, not global.** Mockery defaults to per-mock
ordering (`globally()` opts into one shared cross-mock sequence). RSpec
defaults the other way — confirmed against its docs, not assumed: a single
global sequence across every double in the example, no opt-in verb needed,
with its own docs cautioning the feature is "not generally recommended...
would make your spec brittle, but occasionally useful." This design picks
Mockery's per-double default specifically because it fits this library's
existing architecture with zero new shared state: `DoubleState` is already
fully self-contained per double, and no other feature in the codebase needs
a cross-double registry (see "Module boundaries"). A global-by-default
scope would mean introducing shared mutable state across separate
`TestDouble::for()` calls purely for this one feature.

**Not building an equivalent to `globally()` or named/numbered
`ordered($group)` for v1.** Both exist in Mockery to relax a default this
design is already choosing the more conservative version of. Deliberately
minimal starting surface — add a way to relax the per-double default only
once real usage shows a genuine need, the same policy already applied to
the starting matcher set.

**Mechanism, as built.** `MethodExpectation::inOrder()` is a pure flag
(`isOrdered(): bool`) — no slot number, no back-reference to `DoubleState`.
`MethodExpectation` stays a self-contained value object with zero knowledge
of the double it's registered against (confirmed against its own test
suite, which constructs it standalone with no `DoubleState` in sight).
`DoubleState::orderedExpectations()` derives the ordered subsequence on
demand by filtering the already registration-ordered `$expectations` list —
an expectation's *slot* is simply its position (`array_search()`, strict) in
that filtered list, so no separate numbering bookkeeping exists anywhere.
`DoubleState` also holds one `int $orderCursor` (starts at `0`, the same
sentinel Mockery's own `$_mockery_currentOrder` uses, for the same reason:
the first ordered slot is index `0`, and a slot compared against itself is
never a regression). `ProxyBehavior::enforceOrder()` — a new private method,
separate from `findMatch()` and called only after a match has already been
selected and recorded — is where the actual check lives: a no-op unless the
matched expectation `isOrdered()`; otherwise its slot is looked up and
compared against the cursor, throwing on regression (`slot < cursor`) and
advancing the cursor (`slot >= cursor`) otherwise. Available uniformly on
both `expects()` and `allows()` — no special-casing between them, matching
how every other modifier already applies to both verbs.

**New `OutOfOrderCallException`**, following the exact pattern the other
three mid-test exceptions already use: a plain exception plus a
`PHPUnitOutOfOrderCallException` sibling, picked at throw time by `Engine\
ExceptionFactory`'s existing `class_exists()` check. Names the double, the
offending method, and which already-ordered method it's now behind, e.g.:
`Test double "Connection" received "open()" out of order: "close()" already
happened, and inOrder() requires this to happen no later than that.`

**Terminology note for whoever implements this:** "order" already names a
different concept earlier in this document — "Expectation matching order:
last-registered-that-matches wins" is about declaration order deciding which
expectation gets *selected*. `inOrder()` governs a separate concern: which
already-selected calls are allowed to *happen* in what sequence. Don't
conflate the two in docs or test names for this feature.

**Real-world scope this is for, not a general recommendation:** stateful
protocol boundaries where the order itself is the contract —
`beginTransaction()`/`commit()`, `open()`/`write()`/`close()` on a
connection, "acknowledge before dequeuing the next." Both Mockery's and
RSpec's own source/docs concede this is a common source of brittle tests
when used to pin down incidental implementation order rather than a real
protocol constraint — worth a line in whatever user-facing docs cover this
feature, not just this internal note.

**Built:** `MethodExpectation::inOrder()`/`isOrdered()`,
`DoubleState::orderedExpectations()`/`orderCursor()`/`advanceOrderCursor()`,
`ProxyBehavior::enforceOrder()`, `Exceptions\OutOfOrderCallException` (+
`OutOfOrderCallFields` trait) and its `Integrations\PHPUnit` sibling, and
`ExceptionFactory::outOfOrderCall()`. Covered end-to-end in
`TestDoubleTest` (in-declared-order success, out-of-order regression,
unordered expectations freely interleaved, forward skips allowed, per-double
scoping across two doubles, and parity between `expects()`/`allows()`), plus
unit coverage in `MethodExpectationTest`, `DoubleStateTest`,
`ExceptionFactoryTest`, `PHPUnitExceptionsTest`, and a golden-file message
test in `ExceptionMessagesTest`.

## Strict-by-default scalar/array matching, loose objects, explicit object identity (resolved)

Prior behavior: a bare literal passed to `with()` wrapped in `EqualsMatcher`,
which always compared with `==`, for every type — scalars, arrays, and
objects alike (matched Mockery's own effective default, confirmed against
its source: `Expectation::_matchArg()` checks `===` first as a pure
fast-path, but falls through to `==`, so the *outcome* was always
equivalent to plain `==`).

**Decision: split the default by whether the type has a meaningful
identity-vs-equality gap at all.**

- **Scalars and arrays don't** — for values of the *same* type, `===` and
  `==` never disagree; they only diverge across *differing* types
  (`'0' == 0` but `'0' !== 0`). So defaulting these to `===` removes a real
  type-juggling footgun with no downside: it can never reject a legitimate
  same-type match, only the cross-type surprises. The original case for
  keeping `==` — that this library's own `declare(strict_types=1)`
  discipline makes those surprises rare in practice — doesn't hold once you
  consider that `with()` compares against whatever the *code under test*
  passes, and that code's own `strict_types` adoption isn't this library's
  to assume; `strict_types` usage isn't universal across the PHP ecosystem
  this library gets used against.
- **Objects do** — `==` means "same class, equal properties," `===` means
  "the same instance." Both are genuinely useful defaults for different
  assertions (an equivalent value vs. this exact reference), so this is a
  real choice worth keeping, not a footgun to eliminate. Kept `==` as the
  default here specifically because "an equivalent value was passed" is the
  more common assertion in practice — a strict-by-default `with($book)`
  would reject plenty of correct tests where the code under test
  legitimately reconstructs an equivalent value object before the call.
- For the identity case specifically, a new matcher —
  `Argument::same($expected)`, backed by `===` — rather than changing what a
  bare literal does for objects. Considered `identical()` (php.net's own
  operator-name table calls `==` "Equal" and `===` "Identical") and
  Laravel's `is()` (rejected on its own — `Argument::type()` already reads as
  "is this type of thing," and `Argument::is()` sitting one method away for
  "is this exact instance" would collide the same way `matches()` did against
  `Matcher::matches()` when `satisfies()` was named, above). **Settled on
  `same()`**, matching PHPUnit's own `assertSame()` — the more directly
  relevant prior art, since this library integrates with PHPUnit directly
  and already carries a PHPUnit rosetta-stone table, stronger precedent here
  than reaching for the language manual's operator-name table.

**Built:** `EqualsMatcher::matches()` is now
`is_object($this->expected) ? $this->expected == $actual : $this->expected
=== $actual`. `Argument::same()` is a new one-method addition,
backed by a new `SameMatcher` class, alongside
`any()`/`type()`/`satisfies()`/`capture()`/`remaining()`.

## Method-name suggestions on UnknownMethodException (resolved)

A typo in `expects('sav')`/`allows('sav')`/`received('sav')` used to fail
with a clean "no such method" message and nothing more — every other
diagnostic in this library is held to "explain what to do about it," and
this one didn't, despite being one of the most likely mistakes a person
actually makes.

**Added `Diagnostics\DidYouMean::suggest($needle, $candidates)`:**
Levenshtein-distance-based, with the threshold scaling to the length of
the longer string being compared (`distance <= max(1, floor(maxlen / 3))`,
the same heuristic Symfony Console uses for its own "did you mean"
suggestions) rather than a flat cap — a flat threshold either over-matches
short names or under-matches long ones. Returns `null`, not a guess, when
nothing is close enough: a wrong suggestion is worse than none.
`DoubleState::declarableMethodNames()` supplies the candidate list via
`ReflectionClass::getMethods()` (not `get_class_methods()`, which only
sees public methods from outside the declaring class) so the candidate
set has exactly the same visibility rules `declaringCandidate()`'s own
`method_exists()` check already uses.

**Built:** `UnknownMethodException` gained an optional `?string
$suggestion`, rendered as `Did you mean "save"?` appended to the existing
sentence. Both throw sites (`TestDouble::registerExpectation()` for
`expects()`/`allows()`, and `TestDouble::received()`) compute it the same
way. `expects('bogus')` (nothing close) still renders with no suggestion
appended — the threshold is deliberately conservative rather than always
guessing something.

## Unifying received()'s verification with expects()'s auto-verify domain (resolved)

`received()`'s spy-style check originally lived entirely in
`ReceivedAssertion::__destruct()` — the only way it could support
`with()` then `never()` composed together (nothing about the mechanism
could know "the chain is fully composed" any earlier than object
destruction; a required terminal verb was considered and rejected as
"easy to forget, silently never asserting," and splitting into a second
verb the way Mockery has `shouldNotHaveReceived()` alongside
`shouldHaveReceived()` was rejected on the no-aliases policy). This
worked correctly for the common case — an unassigned statement destructs
at the end of that statement, well within the same test — but had a real
gap: an assertion held somewhere that outlives its own statement (a
property on the test case, not just a local variable — a local variable
turns out *not* to reproduce this, since PHP tears down a method's own
locals the instant the method returns, which is still before
`VerifiesDoubles`'s `#[After]` hook would ever run) has no guaranteed
check point at all.

**Confirmed empirically, not just reasoned about:** reverting the fix and
reproducing with a `ReceivedAssertion` held on a test-case property (not
a local — see above) didn't just silently pass. It crashed the entire
PHPUnit run: the object survived all the way to `TestSuite`'s own
teardown at process end, threw from `__destruct()` at that point, and
PHPUnit reported it as an unattributed internal error, not a failure on
any specific test — worse than the "silently passes" framing this gap
was originally described with.

**Decision: give `received()` the same domain `expects()`/`allows()`
already has, not a separate mechanism.** `TestDouble::$pendingReceived`
mirrors `$pending`'s existing role and lifecycle exactly (same
strong-reference reasoning — an assertion that's purely a local variable
is already garbage-collected before `#[After]` runs, so the pending list
needs a real reference to survive the gap; same reset-on-
`armAutoVerify()`, drain-before-iterate-on-`verifyAll()` pattern).
`ReceivedAssertion::check()` is idempotent (`$checked` flag) so whichever
path — `__destruct()` or the `#[After]`-driven `TestDouble::verifyAll()`
— runs first wins and the other becomes a no-op. `__destruct()` remains
the *only* mechanism for any non-PHPUnit runner, and for PHPUnit users
not using `VerifiesDoubles` — the framework-agnostic promise is
unchanged, this only adds a deterministic backstop for the one setup
(PHPUnit + the trait) that already has a teardown domain to plug into.

**Built:** `TestDouble::$pendingReceived`, `received()` registers into it
while auto-verify is armed, `verifyAll()` drains and checks both lists
from the same call. `ReceivedAssertion::check()` extracted from
`__destruct()`, public (`@internal`), guarded by `$checked`.

## Static methods: rejected cleanly, not silently or with a crash (resolved)

Static methods were always out of `ClassGenerator`'s scope —
`overridableMethods()` skips them, since `ProxyBehavior::intercept()`
dispatches through `$this`, and a static call has no `$this` to give it
(matches Mockery's own posture here: its answer to static mocking,
`alias:`-prefixed mocks, is explicitly not recommended even by Mockery's
own docs, and requires running each such test in its own PHP process
since aliasing a class name is a one-shot, irreversible operation). That
scope decision was always correct. What wasn't caught was what happens at
the two points where a static method actually gets reached, both found by
testing directly rather than assumed from the scope decision alone:

- **Doubling an interface (or abstract class) with a static method
  crashed with an uncatchable PHP fatal error**, not one of this
  library's own exceptions: every interface method is implicitly
  abstract, including static ones, and PHP requires a non-abstract class
  to implement every abstract method it inherits — but
  `overridableMethods()` never emits one for a static method, so the
  generated `final class` was left silently short one required
  implementation. `isFinal()` gets a clean, actionable
  `InvalidDoubleTargetException`; this got nothing, until now.
- **`expects()`/`allows()`/`received()` on a static method that exists on
  a concrete class silently no-op'd.** The method-existence check
  (`declaringCandidate()`) doesn't care about staticness, so the
  expectation registered without error — but the generated subclass
  never overrides a static method at all, so the real implementation ran
  unstubbed regardless of any configured return, and the expectation
  could never be satisfied. The only symptom was a confusing
  `UnsatisfiedExpectationException` later, at `verify()` time — "expected
  exactly 1 time, called 0 times" for a method that genuinely *was*
  called, just not through the interception layer.

**Built:** `ClassGenerator::assertNoAbstractStaticMethods()` (checked
before `eval()`) raises `InvalidDoubleTargetException::
hasAbstractStaticMethod()` for the first case.
`TestDouble::assertConfigurable()` (shared by `registerExpectation()` and
`received()`) checks `DoubleState::isStatic()` and raises the new
`StaticMethodException` for the second, the same way an unknown method
name already is. Actual static-method *interception* remains out of
scope, deliberately, for the same reasons Mockery's own `alias:` mocks
are a documented last resort rather than a first-class feature.

## Magic methods: rejected cleanly, not silently or with a crash (resolved)

Magic methods (`__toString`, `__invoke`, `__call`, even `__construct`/
`__destruct` — anything starting with `__`) were always out of
`ClassGenerator`'s scope: `overridableMethods()` skips any method whose
name starts with `__`, the same exclusion category static methods fall
into. That scope decision was listed in "Known scaffold-era limitations"
as an open gap ("magic methods need explicit, deliberate handling, not
silent non-support") rather than something already resolved — checking it
directly turned up the exact same two failure shapes the static-method
audit above already found and fixed, not a new category of bug:

- **Doubling an interface (or abstract class) with an abstract magic
  method crashed with an uncatchable PHP fatal error**, confirmed
  directly: an interface declaring `__toString(): string` (`Stringable`'s
  own shape), `__invoke()`, or even `__construct()` all produced the
  identical "must therefore be declared abstract or implement the
  remaining methods" fatal `overridableMethods()`'s static-method
  exclusion already causes for a static method — same root cause (an
  abstract method excluded from overriding, PHP requiring a non-abstract
  class to implement every abstract method it inherits), different
  exclusion reason.
- **`expects()`/`allows()`/`received()` on a magic method that exists on a
  concrete class silently no-op'd**, confirmed directly: configuring
  `allows('__toString')->returns('fake')` on a double for a class with a
  real `__toString()` raised no error at all, and `(string) $double` kept
  returning the real value regardless — the identical silent-no-op shape
  the static-method case already had, for the identical reason (the
  generated subclass never overrides the method at all, so the real
  implementation runs unstubbed).

**Built:** `ClassGenerator::assertNoAbstractMagicMethods()` (checked
before `eval()`, right after the static-method check — a method that's
both, `__callStatic` in practice, is already caught by the static check
first) raises `InvalidDoubleTargetException::hasAbstractMagicMethod()` for
the first case. `TestDouble::assertConfigurable()` gained a third check
(`str_starts_with($method, '__')`, after the existence and static checks)
raising the new `MagicMethodException` for the second, mirroring
`StaticMethodException` down to not appending `fabricatedNote()` — the
restriction holds regardless of how the double came to exist. Actual magic
method *interception* remains out of scope, deliberately, for the same
reason static-method interception does: no `$this`-dispatching mechanism
exists for `__call`/`__get`-style dynamic proxying to hook into without a
substantially larger feature than a scaffold-limitations pass warrants —
that's a real gap still, just no longer a silent or crashing one.

**A third, unrelated bug turned up checking `ArrayAccess` specifically —
the other name the original gap listed alongside `__toString`/`__invoke`.**
`ArrayAccess::offsetGet()`/`offsetSet()`/`offsetExists()`/`offsetUnset()`
aren't magic-prefixed (no leading `__`), so none of the above ever applied
to them — they were already being overridden as ordinary methods. But
every method PHP's own internal interfaces declare (`ArrayAccess`,
`Countable`, `Iterator`, and others) has used a *tentative* return type
since PHP 8.1, a mechanism for phasing in a return type without an
immediate BC break: `ReflectionMethod::getReturnType()` returns `null` for
these specifically, confirmed directly against `ArrayAccess::offsetExists`,
even though `hasTentativeReturnType()` is `true` and
`getTentativeReturnType()` gives `bool`. `ClassGenerator::buildMethod()`
only ever called `getReturnType()`, so every generated override of an
`ArrayAccess` method silently lost its return type entirely — PHP accepts
a subclass method with no return type at all, but flags it as a deprecated
incompatibility against the interface's own (tentative) one, on every
single call. **Built:** `buildMethod()` now falls back to
`getTentativeReturnType()` when `getReturnType()` is `null` and
`hasTentativeReturnType()` is `true`. Confirmed to actually catch a
regression, not just pass by construction: reverting the fallback and
rerunning the new `ClassGeneratorTest` regression test reproduces both the
assertion failure and PHPUnit's own deprecation count going non-zero.

## Static analysis: a sound TestDoubleInterface, not a mixin (partially resolved)

"Known scaffold-era limitations" flagged static analysis as a real blind
spot, in two genuinely separate senses that got separated here rather than
solved (or not) as one item:

- **Consumer-facing:** `TestDouble::for()` returned a bare `object`, so a
  consumer running PHPStan/Psalm against their own test code got false
  positives on every double — `Call to an undefined method object::allows()`,
  and a constructor type-hinted against the real target rejecting the double
  entirely (`expects BookRepositoryInterface, object given`), despite both
  being genuinely true at runtime.
- **Library-internal:** `ClassGenerator`'s `eval()` and heavy `Reflection`
  use make this library's own `src/` resistant to static analysis in a way
  no annotation fixes — a tool can't type-check code it can't see until
  runtime.

**Decision: fix the consumer-facing half; leave the library-internal half
deliberately unaddressed**, not silently dropped — this project doesn't run
PHPStan/Psalm against its own source today, so a baseline or targeted
`eval()`-site suppression would be speculative infrastructure for a
scenario nobody's hit yet, the same "no configuration for its own sake"
bias this document applies elsewhere. Revisit if that changes.

**`@mixin` was the first idea, and the wrong one.** `@mixin SomeType` tells
PHPStan/Psalm "this class also responds to `SomeType`'s methods" — but it
names one fixed type at the point it's written. `TestDouble::for()`'s
return type needs to track *whatever class-string argument was passed at
the call site*, which is a generics/templates problem (`@template T`,
`@return T`), not a mixin problem — `@mixin` answers "what else does this
respond to," templates answer "how does the return type relate to the
argument."

**Templates alone don't fully close it either, for two reasons found
working through it:**

- The real return value isn't just `T` — it's `T` plus the six control
  verbs (`expects()`, `allows()`, `strict()`, `passthru()`, `received()`,
  `verify()`) every generated double also has. Those live in
  `Engine\DoubleControlMethods`, a *trait* — and a trait can't appear in a
  PHPStan/Psalm intersection type, only an interface can. **Built:** a new
  public `TestDoubleInterface`, mirroring that trait's six signatures,
  existing purely so `@return T&TestDoubleInterface` has something real to
  point at.
- That new interface had to be genuinely implemented, not just referenced
  in a docblock — an annotation claiming a type the object doesn't actually
  have at runtime is unsound, exactly the "speculation dressed as fact"
  category this document already argues against elsewhere (see the
  "unexpected-call" `Add:`-suggestion discussion). **Built:**
  `ClassGenerator::buildSource()` now always adds `TestDoubleInterface` to
  the generated class's own `implements` clause — `extends Target
  implements TestDoubleInterface` for a class target, `implements
  Target, TestDoubleInterface` for an interface target — so `$double
  instanceof TestDoubleInterface` is a real, checkable fact for every
  double, not a fiction only a type-checker believes. Confirmed directly for
  both code paths, not just the interface one.

**Known imprecision, not a regression:** `$targets` is variadic (a
multi-target intersection double is a real, supported call —
`TestDouble::for(Fillable::class, Sized::class)`), and a single
`@template T` can't cleanly express "each argument contributes to an
intersection" the way it can for the overwhelmingly common single-target
call. The multi-target case falls back to the same untyped `object` a
caller would have gotten before any of this existed — not newly broken,
just not newly improved either. A precise multi-target type would need
per-arity overload signatures, judged not worth the complexity for a rarer
call shape.

**Built:** `TestDoubleInterface` (public, root namespace — same reasoning
`TestDouble` itself lives there, see "Module boundaries"), `ClassGenerator::
buildSource()`'s always-add-the-control-interface change, and `TestDouble::
for()`'s `@template T of object` / `@return T&TestDoubleInterface`
docblock. Covered in `ClassGeneratorTest` (both the `extends` and
`implements` code paths actually produce `instanceof TestDoubleInterface`).

## Second matcher expansion: not(), matches(), contains(), and any(...alternatives) (resolved)

Revisited once `satisfies()` had been in real use long enough to show a
recurring cost: every time someone reached for it to express "not this
value," "matches this string format," or "has this element," the
resulting diagnostic got strictly worse — `PredicateMatcher::describe()`
can only ever render the opaque `satisfies(...)`, with no way to recover
what the closure actually checked. That diagnostic-quality regression,
not speculative catalog-porting, is what motivated this round.

**Benchmarked against Mockery's actual matcher catalog, not assumed —
confirmed by reading `library/Mockery/Matcher/*.php` and the facade
methods on `Mockery.php` directly.** Walked the full list (`any`,
`andAnyOtherArgs`, `type`, `ducktype`, `subset`, `contains`, `hasKey`,
`hasValue`, `capture`, `on`, `mustBe`, `isEqual`, `isSame`, `not`,
`anyOf`, `notAnyOf`, `pattern`) to separate "genuinely missing" from
"reachable through what's already here" or "redundant with a default
this library already has":

- `on()` → already `satisfies()`, same concept under a different name —
  not a gap.
- `mustBe()`/`isEqual()` → already covered by a bare literal's own
  default equality behavior. Mockery's own `MustBe` is
  `@deprecated 2.0 Due to ambiguity, use PHPUnit equivalents` — its own
  maintainers reached the same conclusion, that a dedicated verb here is
  redundant.
- `ducktype()` → discussed explicitly (via a multi-select options prompt
  covering `anyOf`/`notAnyOf`, `subset`/`contains`, `hasKey`/`hasValue`,
  and `ducktype`) and declined as niche enough that `satisfies()`
  remains its only path — a deliberate scope decision, not an
  oversight.
- `subset()` → **partial, not exact.** Mockery's `subset()` checks that
  a whole array contains *all* of several given key/value pairs
  simultaneously (`array_replace_recursive` against the whole
  structure); `contains()` (below) checks whether *any single*
  element/pair satisfies one condition — a different shape. Accepted as
  a deliberate simplification in the interest of keeping the surface
  small, not a full replacement.
- `hasKey()`/`hasValue()` → fully reachable via `contains()`'s callback
  form, with zero information lost.
- `anyOf()`/`notAnyOf()` → the one genuine remaining gap (see `any()`
  below) — nothing in the existing set reaches "this scalar equals one
  of several arbitrary literal values," since every other primitive
  operates on a different shape (type-membership, object identity,
  iterable-containment, regex-string).

**`Argument::not($expected)` / `Argument::not()`.** Two shapes,
disambiguated by `func_num_args()` (not a null-check — `not(null)` is a
legitimate "not null" literal match, not the zero-arg case): one
argument negates a bare literal directly; zero arguments returns a
`NegatedArgument` exposing
`type()`/`same()`/`satisfies()`/`contains()`/`matches()`/`any()`, so
negating a *verb* reads left-to-right (`Argument::not()->type('int')`)
instead of nested inside-out (`Argument::not(Argument::type('int'))`). A
`Matcher` passed directly to the one-argument form is rejected with a
clear error pointing at `not()->verb(...)` — exactly one canonical
spelling per shape, never two ways to negate a matcher, holding the
no-aliases line even through this expansion. `NegatedArgument`
deliberately doesn't mirror every `Argument` verb: `capture()` negated
silently breaks its own capture side-effect (only the top-level matcher
on an argument position is ever checked via `instanceof CaptureMatcher`
— wrapped in `NotMatcher`, it no longer is that top-level matcher), and
`remaining()` is a positional `with()` marker, not a per-value check, so
negating it doesn't mean anything. Confirmed against Mockery's own `Not`
matcher: it only ever compares by identity against one fixed value and
can't wrap another matcher at all — this library's version composes,
Mockery's doesn't.

**`Argument::matches($pattern)`** is `PatternMatcher` under the hood,
matching a string (or `Stringable`) argument against a PCRE pattern.
Named `matches()`, not `pattern()`: the regular expression already *is*
the pattern, so the verb should name the action (matching against it),
not restate the noun a second time. Validated at construction time so a
malformed pattern fails loudly at configuration time rather than as a
silent `preg_match()` warning buried inside call matching later.
Non-string, non-`Stringable` values are a straightforward non-match, not
an uncontrolled `(string)` cast the way Mockery's own `Pattern` matcher
does — an array argument there raises a PHP warning and silently
matches against the literal string `"Array"`.

**`Argument::contains($needle)`** covers what Mockery splits across
`contains()`/`hasKey()`/`hasValue()` (and partially `subset()`, see
above) in one verb: a `Matcher` (true if any element matches it), a
plain callable invoked as `($value, $key)` per element (mirroring
Laravel `Collection::contains()`'s callback form and the `(value, key)`
convention from underscore/lodash-style iteration), or a bare literal
(wrapped in `EqualsMatcher`, same rule as `with()` itself).

**`Argument::any()` widened, not a new `anyOf()`/`notAnyOf()` verb
pair.** No arguments still means "unconstrained, matches everything"
(unchanged, `AnyMatcher`); one or more arguments narrows the domain to
those alternatives (`AnyOfMatcher`, each slot Matcher-or-literal, same
rule as everywhere else). Considered and initially rejected overloading
`any()` on the concern that "matches anything" and "matches any of
these specific things" read as opposite meanings depending on arg count;
revisited and accepted once framed correctly — "any" already means "any
element of a domain that defaults to everything" in plain English, the
same "arity changes the entry point, concept stays the same" pattern
`not()` already established, not an unrelated second meaning bolted onto
the same word. `Argument::not()->any($a, $b)` gives `notAnyOf` semantics
for free through the exact same composition every other `NegatedArgument`
verb already gets — no second verb needed for the negated case either.
Confirmed against Mockery's own `AnyOf`: literal-only, always strict
`===`, no nested-matcher support — this library's version composes and
lets each alternative be its own matcher.

**Built:** `NotMatcher`, `NegatedArgument`, `PatternMatcher`,
`ContainsMatcher`, `AnyOfMatcher`, plus
`Argument::not()`/`matches()`/`contains()`, and `Argument::any()`
widened to variadic.

## Type-signature reconstruction coverage audit (resolved)

"Known scaffold-era limitations" flagged `ClassGenerator`'s type-signature
reconstruction (unions, intersections, enum defaults, variadics, by-ref
params) as needing "its own dedicated compatibility test suite... not just
'tests pass on latest PHP.'" Checked directly rather than assumed, that
framing turned out to be slightly off: the CI matrix already runs the whole
suite across every supported PHP version (8.3–8.5, plus an allow-failure
8.6 nightly), so "does this run on multiple PHP versions" was never
actually the gap. The real gap, found by auditing what each feature's
*existing* tests actually assert:

- **Nullable params, enum defaults, and tentative return types** were
  already precisely tested — reflecting the generated signature/value
  directly, not just observing behavior.
- **Union types and variadic params were only behaviorally tested** —
  called through the double, return value checked, but nothing ever
  reflected the actual reconstructed signature. `UnionTypeInterface` even
  had a second method, `acceptNullableUnion()`, declared specifically for
  the "null as one union member" shape that nothing called or reflected at
  all — a fixture that only looked covered because a sibling method on the
  same interface was tested.
- **Intersection return types were only tested through a different
  subsystem** — `IntersectionReturnInterface::make()` is exercised in
  `LooseModeTest` via Loose mode's recursive-fabrication feature
  (`SafeDefaultResolver`), confirming the *fabricated value* satisfies both
  interfaces, never confirming `ClassGenerator`'s own
  `stringifyIntersectionType()` reconstructed the override's own declared
  return type correctly.
- **By-reference parameters had zero coverage** — `buildParameter()`'s
  `isPassedByReference()` handling existed with no fixture interface
  declaring one at all.

This is exactly the same risk the tentative-return-type bug (see "Magic
methods," `ArrayAccess`) already proved concrete: a behavioral-only test
can pass on every PHP version while the actual generated signature is
subtly wrong, because nothing ever looked at it. **Built:** direct
signature-reflection assertions added for all four (union, nullable-union,
variadic, intersection-return), plus a new `ByRefParamInterface` fixture
and test for the previously uncovered by-ref-parameter case.

**A second, previously-unknown bug turned up during the audit, in a
feature not even on the original list: return-by-reference
(`function &foo()`).** `buildMethod()` never checked
`$method->returnsReference()` at all. Confirmed directly: doubling a
target declaring one crashed with an uncatchable PHP fatal error
("Declaration of ...::getRef(): int must be compatible with &
...::getRef(): int") — the same failure category as the abstract-static
and abstract-magic-method crashes elsewhere in this document, for a third,
unrelated reason a signature can mismatch. Fixing it turned up a second,
smaller issue in the same spot: naively emitting the leading `&` and
otherwise leaving the body as `return $call;` traded the fatal error for a
"Only variable references should be returned by reference" notice on
*every single call*, since `ProxyBehavior::intercept()`'s own result isn't
itself a reference — PHP allows returning a temporary by reference, but
warns about it. **Built:** `buildMethod()` now emits the matching leading
`&` when `returnsReference()` is true, and — only for that case — assigns
the intercepted result to a real local variable first
(`$__td_result = ...; return $__td_result;`) rather than returning the
call expression directly, which is what a by-ref return actually needs to
stay silent. Confirmed both fixes actually catch a regression, not just
pass by construction: reverting the `&` emission reproduces the original
fatal error (severe enough to kill the PHPUnit process outright, not just
fail a test); reverting the local-variable body change reproduces the
notice.

**Built:** signature-reflection tests for union/nullable-union/variadic/
intersection-return types; `ByRefParamInterface` and `RefReturnInterface`
fixtures; `ClassGenerator::buildMethod()`'s by-reference-return handling
(leading `&` plus the local-variable body). All in `ClassGeneratorTest`.
