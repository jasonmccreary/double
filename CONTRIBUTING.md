# Contributing

## No aliases (standing policy)

This library has exactly one canonical verb per concept: `expects()` / `allows()`
for configuration, `returns()` / `throws()` / `resolves()` for outcomes,
`with()` for argument constraints, counts (`times()`, `atLeastOnce()`,
`never()`), and `received()` for spy-style assertions. There are no aliases
for any of these — not for familiarity with Mockery, PHPUnit's native
mocks, Prophecy, or Phake, and not ever.

Counts specifically: `times()` is overloaded (`times(3)` exact,
`times(1, 3)` between, `times(minimum: 2)` at least, `times(maximum: 5)` at
most) rather than growing into `once()`/`twice()`/`atMost()`/`between()` as
separate words. `never()` is the one exception kept as its own verb rather
than folded into `times(0)`.

A PR that adds a convenience alias (e.g. `andReturn()` next to `returns()`, or
`shouldReceive()` next to `expects()`) will be declined, even though "add a
familiar alias for people migrating from X" reads as helpful on its face. Every
other PHP mocking library accumulated its API exactly this way — one library, four
verbs for the same concept, forever. Migration help for people coming from
Mockery or PHPUnit's native mocks belongs in documentation (a rosetta-stone
table), not in the API surface.

If you think a concept is genuinely missing a verb (not an alias for one that
already exists), open an issue to discuss it before sending a PR.

## Public API stability

Two things are frozen as semver-guaranteed public API, not internal details
that merely happen to be reachable: the `Matcher` interface (`matches()`,
`describe()`, `explainMismatch()`) and the public readonly fields on every
concrete `TestDoubleException` subclass. Practically: a PR widening `Matcher` itself,
or renaming/removing/retyping a field on an existing exception, is a
major-version change and needs to be flagged as one — reach for an
additive, optional interface (e.g. `ExplainsWithDetail extends Matcher`)
instead when a matcher genuinely needs to convey more. `Argument` (the
static facade) is not covered by this freeze and can keep growing
incrementally.

## Module boundaries

The codebase is split into `JMac\Testing\Engine`, `JMac\Testing\Matching`,
`JMac\Testing\Diagnostics`, and `JMac\Testing\Exceptions`. Only `Engine` is
allowed to depend on the others. `Diagnostics` has zero dependencies on the
rest of the library — it's the shared home for rendering/formatting logic
(`ValueFormatter`, `ArgumentFormatter`, `Pluralizer`) that more than one other
module needs, specifically so that logic has exactly one implementation
instead of being hand-duplicated per module. `Matching` and `Exceptions` each
depend only on `Diagnostics`, nothing else. A PR that introduces a dependency pointing the wrong
direction (e.g. `Matching` referencing `Engine` directly, or `Diagnostics`
referencing anything) will need to be restructured before it can be merged.
