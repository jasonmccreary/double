---
title: What about method-level `passthru`?
description: Mockery lets one stubbed expectation run the real method. Double doesn't, and the reason isn't a missing feature — it's what that need is usually actually asking for.
published: 2026-09-14
---

# What about method-level `passthru`?

```php
$mock->shouldReceive('log')->passthru();
```

Mockery's `passthru()` — the actual feature Double's own `passthru()` borrowed its name from — is a per-expectation verb, not a whole-mock mode. It means: this one stubbed call still counts as received, but instead of returning a canned value, run the real method and return whatever that produces. Double has nothing that reads this way, and it's worth explaining why, because the answer isn't "we haven't gotten to it yet."

## The shape this actually wants

Every double already generates a real, callable body for every method on the class it doubles — that's what makes whole-double [passthru mode](../03-creating-doubles.md#passthru) work at all: unstubbed calls run for real, on the double itself. So a method-level equivalent would be almost free to build — `allows('log')->passthru()` could just call that same real body that already exists internally. The capability isn't the obstacle. The question is whether it's solving a real problem.

Mockery's per-expectation `passthru()` answers a specific shape of need: "this double is mostly fake — safe defaults, explicit expectations — but for this one call, run the real thing instead." Double already covers the *opposite* shape cleanly: mostly real, with the few methods you care about faked out. If you want `log()` to genuinely run while everything else on the same double is fake, why would you reach for a fake-by-default double at all? Flip it: start from `passthru()`, and stub the handful of methods you actually want to intercept. Everything you don't stub already runs for real — that's the whole mode.

## Why it's not quite a free inversion, and why that's the tell

It's tempting to treat these as pure duals — De Morgan's for test doubles: "mostly real except X" and "mostly fake except X" should be interchangeable framings of the same set. They're not quite, and the gap is instructive. Passthru's default for anything unconfigured is "run real"; Loose's default is "safe value." Going from "fake by default, real by exception" to "real by default, fake by exception" isn't symmetric effort — if you actually wanted a mostly-fake double with one real exception, achieving it via whole-double passthru means defensively stubbing away *every other* method so they don't also start running real code. Miss one, and it silently executes for real instead of returning a safe default.

That asymmetry is the tell. A genuine need for "isolate this real call from an otherwise fully-faked object" is a need to spot-check that a stub hasn't drifted from the real implementation it's standing in for — checking the fake against the real from inside the same test, rather than trusting that the real method already has its own coverage elsewhere. That's the same shape of problem [the AWS SDK and Redis examples](why-doesnt-double-mock-magic-methods.md) ran into from the other direction: reaching for a narrower, more honest seam produces a stronger test than papering over the gap with more mocking machinery. There, the fix was doubling what a method actually delegates to, or using the real library's own fake transport, instead of asking Double to stub something it couldn't verify existed. Here, the fix is the same instinct pointed the other way: if a method's real behavior matters enough that a test needs to observe it running for real, that's a sign that method deserves its own real, direct test — not a one-off exception carved into an otherwise-fake double for one test to lean on.

## What to reach for instead

`resolves()` already covers the rare case where you genuinely have a real object on hand and want one call to run against it:

```php
$repository->allows('calculateTax')->resolves(fn (...$args) => $realGateway->calculateTax(...$args));
```

This is arguably more honest than Mockery's version, too — it doesn't hide which real object is running behind a bare `passthru()`, it names it. Beyond that, if you're finding yourself wanting a whole class to be "fake except this one call" often enough to miss the shorthand, that's usually the moment to flip the double to `passthru()` instead and name the few things you actually want to fake — which was the answer this whole page started with.
