---
title: How to handle method chains in Double?
description: Mockery lets you stub a whole call chain as one dotted string. Double doesn't, and the fix isn't a missing feature — it's naming the thing the string was hiding.
published: 2026-09-28
---

# How to handle method chains in Double?

```php
$notifiable->shouldReceive('routeNotificationFor->create')->with([...]);
```

Mockery reads this as: stub `routeNotificationFor()` to return something, then stub `create()` on that something, with these arguments. One line, two objects. Double has no equivalent — `expects('routeNotificationFor->create')` isn't a method name Double recognizes, and there's no chain syntax in `with()` or `expects()`. That's deliberate.

## What the string is actually standing in for

`expects()` works by knowing the real method's signature — what it takes, what it returns, whether a typo in the name should fail loudly. That's what makes arity checking possible, and what makes "did you mean `sendEmail`?" a real error instead of a silent pass. A chain string throws that away for everything after the first `->`. Double would have to call the first segment, then just hope the result declares `create()` — no reflection, no arity check, no way to catch a typo in the second half.

To check any of that, Double needs to know what `routeNotificationFor()` actually returns — here, `MorphMany`. Once you know that, the honest version already exists, and it doesn't need new syntax:

```php
$route = Double::for(MorphMany::class); // the real return type of routeNotificationFor()
$route->expects('create')->with([...]);
$notifiable->allows('routeNotificationFor')->returns($route);
```

Same shape as the chain string, just written down instead of implied. The chain syntax never saved you the work of knowing that return type — it just let you skip writing it, right up until the double at the far end needed its own expectations or return values. At that point you're back to a real variable anyway.

## The same trade as everywhere else in Double

This is the same shape of decision as [why Double won't stub `__call()`-only classes](why-doesnt-double-mock-magic-methods.md): a shorthand that works by not checking anything, against an explicit form that costs a couple more lines but stays honest about what it's asserting. `routeNotificationFor->create` is really one string doing the job of two typed doubles — Mockery's flexibility comes from never verifying the middle of the chain matches anything real. Double's answer is to name that middle step as a real, typed double instead.

It also comes down to one verb per concept. `expects()` takes a method name, not a small parser's worth of embedded grammar. A `->` inside that string would be the one exception to "no aliases, no nuances" in the whole library, for a shorthand that only ever saves a variable name.

## What to reach for instead

Split the chain at its first hop, name the real return type, and configure each double normally:

```php
$route = Double::for(MorphMany::class);
$route->expects('create')->with([...]);

$notifiable = Double::for(Notifiable::class);
$notifiable->allows('routeNotificationFor')->returns($route);
```

If the chain is three calls deep, it's three doubles deep — nothing new to learn at each step, just `Double::for()` and `expects()` repeated. The extra lines also pay for themselves: if `routeNotificationFor()`'s return type ever changes, `Double::for(MorphMany::class)` catches it immediately, where a bare chain string would keep "working" against whatever `create()` happened to exist on the wrong object.

One thing worth flagging separately: this isn't something an automated Mockery-to-Double converter can safely do on its own, since splitting a chain string requires knowing the real return type of the first call. Shift's converter currently leaves `expects('a->b')` untouched, without flagging it for a human to look at. That's a gap worth closing on the converter side, independent of whether Double ever adds chain syntax of its own — which it isn't planning to.
