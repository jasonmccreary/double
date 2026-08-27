---
title: Why not hasReceived() or assertReceived()?
description: Why Double settled on received() instead of a has- or assert-prefixed verb, and why that was a deliberate trade-off.
published: 2026-08-12
---

# Why not `hasReceived()` or `assertReceived()`?

```php
$repository->received('save')->with($book);
```

Read aloud, that line is already a sentence: "repository received save with book." That's not an accident — every verb in Double's API was picked to read this way. Which is exactly why the question comes up: `hasReceived()` and `assertReceived()` are the prefixes you'd expect from a testing library, so `received()` on its own can look like something's missing.

Nothing's missing. I'd rather give up a filler word — even if it leaves a slightly less complete-sounding English sentence — than let that word introduce ambiguity into what the code actually does.

## One word, no strays

I gave a lot of thought to the terms used within Double. I wanted them to be one word, and I didn't want them to stray too far from the norm. `expects`, `allows`, `received`, `verify` are all common words within mocking libraries — nothing here is invented vocabulary.

Generally speaking, I tend not to use filler words anymore. They might make sense initially, but over time you realize they're often cost — extra characters — without benefit, especially in a typed language. You'll see this in Laravel: properties named `hidden` instead of `isHidden`. As a bool, `is` isn't adding much.

## The filler word that creates ambiguity

Bringing it back to mocking libraries, these filler words can actually create *ambiguity*. Consider Mockery's `shouldReceive`:

> **`shouldReceive` — read as `does`, actually means `can`**
>
> Some people read `should` as `does`. But in fact it's closer to `can` — so, in Mockery, `shouldReceive` means "may or may not receive." This filler word might have led to the biggest misconception/misuse of Mockery.
>
> `does` (certain) sits at one end, `can` (maybe) at the other — and `should` lands a lot closer to `can` than most people assume.

That's the failure mode a prefix invites: it reads as a claim about certainty, but it isn't one. `received()` doesn't have that problem, because it isn't claiming anything — it's just naming the fact you're checking. There's no tense or modal verb to misread, because there's no verb standing in front of the one that matters.
