<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

use JMac\Testing\Diagnostics\AmbiguousExpectation;
use JMac\Testing\Diagnostics\Pluralizer;

/**
 * The properties, constructor, and message for "two or more expectations on
 * the same method, with identical argument matching, each with their own
 * finite call limit" — shared with
 * Integrations\PHPUnit\PHPUnitAmbiguousExpectationException via a trait (see
 * UnsatisfiedExpectationFields for the same split).
 */
trait AmbiguousExpectationFields
{
    /**
     * @param  list<AmbiguousExpectation>  $ambiguities
     */
    public function __construct(
        public readonly string $label,
        public readonly array $ambiguities,
        public readonly bool $fabricated = false,
    ) {
        parent::__construct(self::renderMessage($label, $ambiguities, $fabricated));
    }

    /**
     * @param  list<AmbiguousExpectation>  $ambiguities
     */
    public static function renderMessage(string $label, array $ambiguities, bool $fabricated): string
    {
        if (count($ambiguities) === 1) {
            return self::renderSingle($label, $ambiguities[0], $fabricated);
        }

        return self::renderMultiple($label, $ambiguities, $fabricated);
    }

    private static function renderSingle(string $label, AmbiguousExpectation $ambiguity, bool $fabricated): string
    {
        $message = sprintf(
            "Double `%s` has `%s(%s)` registered %s. This looks like an attempt to return values in sequence. To be explicit about the order, combine them into one expectation instead.\n\n".
            "For example: `%s(...)->times(%d)->returns(...)`.",
            $label,
            $ambiguity->method,
            $ambiguity->argumentsDescription,
            Pluralizer::pluralize($ambiguity->count, 'time', 'times'),
            $ambiguity->method,
            $ambiguity->count,
        );

        return DoubleException::appendFabricatedNote($message, $fabricated);
    }

    /**
     * @param  list<AmbiguousExpectation>  $ambiguities
     */
    private static function renderMultiple(string $label, array $ambiguities, bool $fabricated): string
    {
        $count = count($ambiguities);

        $message = sprintf(
            "%s registered ambiguously on double `%s`:\n\n%s\n\n".
            "Each looks like an attempt to return values in sequence. To be explicit about the order, combine each into one expectation instead, using `times(n)->returns(...)`.",
            Pluralizer::pluralize($count, 'method was', 'methods were'),
            $label,
            implode("\n", array_map(
                static fn (AmbiguousExpectation $ambiguity): string => sprintf(
                    '    `%s(%s)`, registered %s',
                    $ambiguity->method,
                    $ambiguity->argumentsDescription,
                    Pluralizer::pluralize($ambiguity->count, 'time', 'times'),
                ),
                $ambiguities,
            )),
        );

        return DoubleException::appendFabricatedNote($message, $fabricated);
    }
}
