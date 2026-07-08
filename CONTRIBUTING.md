# Contributing

## No aliases (standing policy)

This library has exactly one canonical verb per concept: `expects()` / `allows()`
for configuration, `returns()` / `throws()` / `returnsUsing()` for outcomes,
`with()` for argument constraints, counts (`once()`, `twice()`, `times()`,
`atLeastOnce()`, `never()`), and `received()` for spy-style assertions. There are
no aliases for any of these — not for familiarity with Mockery, PHPUnit's native
mocks, Prophecy, or Phake, and not ever.

A PR that adds a convenience alias (e.g. `andReturn()` next to `returns()`, or
`shouldReceive()` next to `expects()`) will be declined, even though "add a
familiar alias for people migrating from X" reads as helpful on its face. Every
other PHP mocking library accumulated its API exactly this way — one library, four
verbs for the same concept, forever. Migration help for people coming from
Mockery or PHPUnit's native mocks belongs in documentation (a rosetta-stone
table), not in the API surface.

If you think a concept is genuinely missing a verb (not an alias for one that
already exists), open an issue to discuss it before sending a PR.

## Module boundaries

The codebase is split into `TestDouble\Engine`, `TestDouble\Matching`,
`TestDouble\Diagnostics`, and `TestDouble\Exceptions`. Only `Engine` is allowed to
depend on the others — `Matching` and `Diagnostics` have zero dependencies on the
rest of the library, and `Exceptions` depends only on `Diagnostics`. See
`ARCHITECTURE.md` for the full reasoning. A PR that introduces a dependency
pointing the wrong direction (e.g. `Matching` referencing `Engine`) will need to
be restructured before it can be merged.
