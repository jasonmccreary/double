<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

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
    public function __construct(
        public readonly string $label,
        public readonly string $method,
        public readonly string $argumentsDescription,
        public readonly bool $fabricated = false,
    ) {
        parent::__construct(self::renderMessage($label, $method, $argumentsDescription, $fabricated));
    }

    public static function renderMessage(string $label, string $method, string $argumentsDescription, bool $fabricated): string
    {
        return sprintf(
            'Test double `%s` got an unexpected call to `%s(%s)` — Strict mode requires every '
            .'call configured. Add: $%s->allows(\'%s\')->returns(...);%s',
            $label,
            $method,
            $argumentsDescription,
            TestDoubleException::suggestedVariableName($label),
            $method,
            TestDoubleException::fabricatedNote($fabricated),
        );
    }
}
