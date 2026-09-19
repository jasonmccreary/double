---
title: When does a test not need a double at all?
description: A Mockery test that mocked too shallow, a Double test that had to go one layer deeper to stay honest, and a third version that needed no mocking at all — three attempts at the same test, in order.
published: 2026-10-06
---

# When does a test not need a double at all?

```php
$factory = Mockery::mock(Factory::class);
$factory->expects('choice')->with('Test', $expectedOptions, $expectedDefault)->andReturn($return);
```

This is `ConfiguresPromptsTest.php`, from Laravel's own framework test suite — verifying that `select()`/`multiselect()` fall back correctly to a plain console prompt when a real terminal isn't available (CI, `windows_os()`, `php artisan` piped into something). It reads like a reasonable mock. It's also mocking the wrong thing, in a way that took two more attempts to see.

## Attempt 1: Mockery, mocking the surface

`$this->components` in Laravel's `ConfiguresPrompts` trait is an `Illuminate\Console\View\Components\Factory`. Its `choice()` method doesn't exist as a real, declared method — `Factory::__call()` builds it on the fly:

```php
public function __call($method, $parameters)
{
    $component = '\Illuminate\Console\View\Components\\'.ucfirst($method);

    return (new $component($this->output))->render(...$parameters);
}
```

Every call constructs a fresh `Choice` object and immediately discards it. Mockery doesn't care — a Mockery mock is itself built on `__call()`, so it will happily answer to a method name that was never real. `$factory->expects('choice')->with(...)` "works," but only in the sense that it intercepts a string. `Factory::__call()`'s dynamic class resolution and the real `Choice` component's argument handling never run. The whole class under test is replaced by a mock before any of its logic executes.

## Attempt 2: Double, honestly refusing to pretend

Converting this to Double surfaces that gap immediately, by name:

```
Can't configure `choice` on a double for `Illuminate\Console\View\Components\Factory`
because it doesn't declare this method.
```

Double won't stub a method it can't verify exists — [that line is deliberate](why-doesnt-double-mock-magic-methods.md), not a missing feature. For classes that forward `__call()` to something already injected (Redis connections, HTTP client factories), the fix is to double whatever's concretely behind the forwarding call. `Factory` doesn't have that shape — nothing is behind `choice()` except a class name built from the method name, gone the instant `render()` returns. No collaborator to redirect to.

The fix that shipped: don't double `Factory` at all. Construct a real one, wrapping an already-doubled collaborator one level down:

```php
$outputStyle = Double::for(OutputStyle::class);
$factory = new Factory($outputStyle); // real, not doubled

$outputStyle->expects('askQuestion')->with(Argument::satisfies(
    fn ($question) => $question->getQuestion() === 'Test'
        && $question->getChoices() === $expectedOptions
        && $question->getDefault() === $expectedDefault
        && $question->isMultiselect() === false
))->returns($return);
```

This is a genuinely better test than the Mockery one. `Factory::__call()`'s real dispatch and the real `Choice` component's translation into a `ChoiceQuestion` object both actually run now. A typo in the component's namespace, or a bug in how `Choice` builds that question, would be invisible to the mocked version and caught here. Same move as the Redis case one post back: prefer the real, narrow seam over a wider one that only looks like the real class.

It still felt like a downgrade to write, though. The assertion isn't about the call you made anymore — it's about the shape of an object constructed two layers down, reached through a boolean chain inside `Argument::satisfies()`. You're inferring "I called `choice()` with these arguments" from a `Question`'s getters instead of asserting it directly. That's a real cost, even buying real coverage.

## Attempt 3: asking whether either of them should have mocked anything

The felt cost of attempt 2 is worth taking seriously, but not by reaching for a bigger double. `OutputStyle::askQuestion()` is a real, declared method — nothing magic about it — and it calls straight into Symfony's `SymfonyStyle::askQuestion()`, which reads from a real `QuestionHelper` against a real `InputInterface`. Symfony ships its own answer to "how do I test code that asks the terminal a question," the same way the AWS SDK ships `MockHandler` for testing code that calls a client: feed a real input stream, let everything above it run for real.

```php
$app = new Application(__DIR__);
$app->instance('env', 'testing'); // not a double — real Laravel test bootstrapping

$command->setLaravel($app);

$input = new ArrayInput([]);
$stream = fopen('php://memory', 'w+');
fwrite($stream, "b\n"); // what the user "typed"
rewind($stream);
$input->setStream($stream);

$command->run($input, new BufferedOutput());
```

No `Double::for()`. No `Mockery::mock()`. `$command->run()` calls the real `Command::run()`, which resolves a real `OutputStyle` and a real `Factory` from the real container, because nothing is stubbing `Application::make()` anymore. `ConfiguresPrompts::configurePrompts()` runs for real, `Prompt::fallbackWhen()` correctly evaluates to true off the real `runningUnitTests()` check, `Factory::choice()`'s real dynamic dispatch runs, the real `Choice` component builds a real `ChoiceQuestion`, and Symfony's real `QuestionHelper` reads `"b\n"` off the stream and returns `'b'` — exactly what the test expects.

It holds up against the harder cases in the original data providers too, not just the easy one:

```php
// numeric keys, hit enter for the default
run(fn () => select('Test', [1 => 'a', 2 => 'b', 3 => 'c'], 2), '');
// => int(2)

// multiselect by index
run(fn () => multiselect('Test', ['a', 'b', 'c'], required: true), '1,2');
// => ['b', 'c']

// multiselect, hit enter for the default
run(fn () => multiselect('Test', ['a' => 'A', 'b' => 'B', 'c' => 'C'], ['b', 'c']), '');
// => ['b', 'c']
```

All three match the original expectations exactly. The numeric-key, associative, and list variations, plus defaults and multiselect's comma-separated answer format, all come out of Symfony's real answer parsing correctly, with nothing configured to make that happen.

There's a second effect neither the Mockery nor the Double version had: the whole double-setup harness disappears with it. Once `Application` is real — given only the one binding it needs — `make()` genuinely resolves `OutputStyle`/`Factory`, `call()` genuinely invokes `handle()`, `runningUnitTests()` genuinely returns the right thing, and `OutputStyle::newLinesWritten()` genuinely tracks itself. None of the four stubs both prior versions needed for pure scaffolding are needed at all. This isn't the same test with fewer mocks. It's less test code, full stop, because the thing making both prior versions complicated was mocking a container that didn't need to be mocked in the first place.

## The pattern underneath this

Three attempts, three different answers to what's actually being verified here:

1. **Mockery** — mocked the outermost call. Passed, but proved almost nothing: neither `Factory`'s real dispatch nor the real component's argument handling ever ran.
2. **Double** — refused to mock a method that doesn't exist, forcing the double one layer deeper, onto a real seam (`askQuestion()`). The resulting test is honestly stronger, even though writing it felt like more work for a worse-looking assertion.
3. **Real stream, no doubles** — asked whether the layer being doubled needed to exist as a double at all. It didn't. Symfony already ships the real mechanism for exactly this.

Step 2 wasn't wrong. It's what happens when Double's rule — nothing gets stubbed that isn't real and declared — runs into a class with no real seam at the layer you first reached for. It's *supposed* to feel like resistance when the thing you're trying to double was never a good target to begin with. The lesson isn't "go one layer deeper and stop." It's that hitting resistance at one layer is worth pausing on — the same way `Double::for(SesClient::class)` failing was worth pausing on — not to find a way around the rule, but to ask, once more, whether the layer you're standing on is the right one to fake at all.
