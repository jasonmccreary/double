# Double

Double is a PHP test double library with one fluent API for stubs, mocks, and spies.

## Creating Doubles

- Import `JMac\Testing\Double` and create a double with `Double::for(ClassOrInterface::class)`.
- A plain double is loose: unmatched calls return safe values based on the method's return type.
- Use `->strict()` when unmatched calls should fail immediately.
- Use `->passthru($realInstance)` when unmatched calls should delegate to a real object. Configured and delegated calls are both recorded.
- Omit the argument to `->passthru()` to auto-instantiate the target, or to reuse the real instance already passed to `Double::for()`.
- Choose strict or passthru mode immediately after `Double::for()`.

@verbatim
<code-snippet name="Creating doubles" lang="php">
use JMac\Testing\Double;

$repository = Double::for(BookRepository::class);
$strictRepository = Double::for(BookRepository::class)->strict();
$logger = Double::for(Logger::class)->passthru($realLogger);
$service = Double::for($realService)->passthru();
</code-snippet>
@endverbatim

## Configuring Calls

- Use `expects()` when the call is part of the assertion. It requires exactly one matching call by default.
- Use `allows()` for response setup that may be called any number of times, including zero.
- Use `with()` to constrain arguments. Omitting it matches any arguments; `with()` with no arguments matches a zero-argument call.
- Use `returns()`, `throws()`, or `resolves()` to configure the outcome.
- Use `times()` for call counts, `never()` for zero calls, and `ordered()` only when call order is part of the contract.
- Register broad fallbacks before specific overrides because the last matching expectation wins.

@verbatim
<code-snippet name="Configuring a double" lang="php">
$repository->allows('find')->returns(null);
$repository->expects('find')->with(123)->returns($book);
$repository->allows('save')->throws(new ReadOnlyException());
$repository->expects('flush')->times(2);
</code-snippet>
@endverbatim

## Matching Arguments

- Plain values usually suffice. Scalars and arrays use strict equality; objects use value equality.
- Import `JMac\Testing\Matching\Argument` for flexible matching.
- Common matchers include `Argument::any()`, `Argument::type()`, `Argument::same()`, `Argument::satisfies()`, `Argument::capture()`, `Argument::remaining()`, `Argument::matches()`, and `Argument::contains()`.
- Prefer `Argument::same($object)` when object identity matters.

@verbatim
<code-snippet name="Matching arguments" lang="php">
use JMac\Testing\Matching\Argument;

$repository->expects('find')->with(Argument::type('int'));
$repository->allows('save')->with(Argument::satisfies(fn (Book $book) => $book->isValid()));
</code-snippet>
@endverbatim

## Verification

- Use `received('method')` for post-hoc assertions. It means at least once by default and supports `with()`, `times()`, and `never()`. It self-verifies once the assertion falls out of scope, so no extra call is needed to trigger it.
- Use `unused()` to assert that no method was called on the double.
- `expects()`/`allows()` expectations need an explicit check: PHPUnit and Pest projects should use `JMac\Testing\Integrations\PHPUnit\VerifiesDoubles` on their base test case, which verifies all expectations after each test.
- Without the trait, call `$double->verify()` before the test ends to check `expects()`/`allows()` expectations.

@verbatim
<code-snippet name="Automatic PHPUnit verification" lang="php">
use JMac\Testing\Integrations\PHPUnit\VerifiesDoubles;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    use VerifiesDoubles;
}
</code-snippet>
@endverbatim

## Constraints

- Do not use Mockery aliases such as `shouldReceive()`, `andReturn()`, `once()`, or `twice()`; use Double's canonical verbs.
- Final classes cannot be doubled, and static methods cannot be configured or verified.
- The target cannot declare Double's reserved control methods: `expects`, `allows`, `strict`, `passthru`, `received`, `unused`, or `verify`.
