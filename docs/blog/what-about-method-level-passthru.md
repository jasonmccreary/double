---
title: What about method-level `passthru`?
description: Mockery lets one stubbed expectation run the real method. Double doesn't, and the reason isn't a missing feature — it's what that need is usually actually asking for.
published: 2026-09-14
---

# What about method-level `passthru`?

```php
$mock->shouldReceive('log')->passthru();
```

Mockery's `passthru()` — the feature Double's own `passthru()` borrowed its name from — is a per-expectation verb, not a whole-mock mode. It means: this stubbed call still counts as received, but instead of a canned value, run the real method and return whatever that produces. Double has nothing that reads this way, and it's worth explaining why, because the answer isn't "we haven't gotten to it yet."

## The shape this actually wants

Every double already generates a real, callable body for every method on the class it doubles — that's what makes whole-double [passthru mode](../03-creating-doubles.md#passthru) work at all: unstubbed calls run for real, on the double itself. A method-level equivalent would be almost free to build; `allows('log')->passthru()` could just call that same real body. The capability isn't the obstacle. The question is whether it's solving a real problem.

Mockery's per-expectation `passthru()` answers a specific need: "this double is mostly fake — safe defaults, explicit expectations — but for this one call, run the real thing instead." Double already covers the opposite shape cleanly: mostly real, with the few methods you care about faked out. If you want `log()` to genuinely run while everything else on the same double is fake, why reach for a fake-by-default double at all? Flip it — start from `passthru()`, and stub the handful of methods you actually want to intercept. Everything else already runs for real.

## Why that flip isn't quite free, and why that's the tell

Passthru's default for anything unconfigured is "run real." Loose's default is "safe value." Going from fake-by-default to real-by-default isn't symmetric effort: if you actually wanted a mostly-fake double with one real exception, getting there via whole-double passthru means defensively stubbing away every other method so they don't also start running real code. Miss one, and it silently executes for real instead of returning a safe default.

That asymmetry is the tell. A genuine need to isolate one real call inside an otherwise fully-faked object is really a need to spot-check that a stub hasn't drifted from the real implementation it stands in for — checking the fake against the real, from inside the same test. That's the same shape of problem [the AWS SDK and Redis examples](why-doesnt-double-mock-magic-methods.md) ran into from the other direction: reaching for a narrower, honest seam beats papering over the gap with more mocking machinery. There, the fix was doubling what a method actually delegates to, or using the real library's own fake transport. Here it's the same instinct pointed the other way: if a method's real behavior matters enough that a test needs to watch it run for real, that method deserves its own real, direct test — not a one-off exception carved into an otherwise-fake double.

## What to reach for instead

`resolves()` already covers the rare case where you have a real object on hand and want one call to run against it:

```php
$repository->allows('calculateTax')->resolves(fn (...$args) => $realGateway->calculateTax(...$args));
```

This is arguably more honest than Mockery's version, too — it doesn't hide which real object is running behind a bare `passthru()`, it names it. And if you're finding yourself wanting a whole class to be "fake except this one call" often enough to miss the shorthand, that's the moment to flip the double to `passthru()` instead and name the few things you actually want to fake.
