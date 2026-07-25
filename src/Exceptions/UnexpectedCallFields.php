<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

use JMac\Testing\Diagnostics\CallListFormatter;

/**
 * The properties, constructor, and message for "Strict mode got an
 * unexpected call" — shared, by both UnexpectedCallException and
 * Integrations\PHPUnit\PHPUnitUnexpectedCallException, since a trait is the
 * only way PHP lets two classes with different, already-fixed parents
 * (TestDoubleException vs. AssertionFailedError — see ARCHITECTURE.md's
 * "PHPUnit integration") share real code instead of hand-duplicating it.
 * Each class still gets its own real, independent instance — this only
 * removes the duplication, not the two-classes-per-diagnostic split itself.
 *
 * parent::__construct(...) below resolves against whichever class actually
 * uses this trait — standard PHP trait semantics, not something special to
 * this codebase. TestDoubleException:: (rather than self::) is used for the
 * two shared-prose helpers because PHPUnitUnexpectedCallException doesn't
 * extend TestDoubleException and so doesn't inherit them; explicit
 * TestDoubleException:: reaches the exact same final static methods either
 * way, since they were never overridable in the first place.
 */
trait UnexpectedCallFields
{
    /**
     * @param  list<string>  $otherObservedCalls  every other call already observed for this
     *                                            method (matched or not, same plain-fact rule
     *                                            UnsatisfiedExpectation::$otherObservedCalls
     *                                            uses), excluding this failing call itself —
     *                                            see ARCHITECTURE.md's "Symmetric extension"
     */
    public function __construct(
        public readonly string $label,
        public readonly string $method,
        public readonly string $argumentsDescription,
        public readonly bool $fabricated = false,
        public readonly array $otherObservedCalls = [],
    ) {
        parent::__construct(self::renderMessage($label, $method, $argumentsDescription, $fabricated, $otherObservedCalls));
    }

    /**
     * @param  list<string>  $otherObservedCalls
     */
    public static function renderMessage(
        string $label,
        string $method,
        string $argumentsDescription,
        bool $fabricated,
        array $otherObservedCalls = [],
    ): string {
        $message = sprintf(
            'Test double `%s` received an unexpected call to `%s(%s)`. Strict mode requires every call to be configured.',
            $label,
            $method,
            $argumentsDescription,
        );

        // A configuration suggestion only makes sense when there's nothing better to
        // offer. It's a guess on three separate axes at once — the variable name
        // (derived from the label, not the test's actual code), the return value
        // (`...` is a placeholder, not real code), and the verb itself (allows() vs.
        // expects() is a real decision this library can't make on the caller's
        // behalf) — so once real, fact-based correlation data exists, that guess
        // gets dropped rather than sitting next to a stronger, non-speculative fact.
        $message .= $otherObservedCalls !== []
            ? "\n\n".CallListFormatter::renderCorrelationParagraph($method, $otherObservedCalls)
            : ' '.self::renderSuggestion($label, $method);

        return TestDoubleException::appendFabricatedNote($message, $fabricated);
    }

    private static function renderSuggestion(string $label, string $method): string
    {
        return sprintf(
            'For example: `$%s->allows(\'%s\')->returns(...)`.',
            TestDoubleException::suggestedVariableName($label),
            $method,
        );
    }
}
