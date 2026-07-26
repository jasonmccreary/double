<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

use JMac\Testing\Diagnostics\CallListFormatter;

/**
 * The properties, constructor, and message for "unused() found calls
 * already recorded on the double" — shared with
 * Integrations\PHPUnit\PHPUnitUnusedAssertionException via a trait, same
 * reasoning as UnexpectedCallFields.
 */
trait UnusedAssertionFields
{
    /**
     * @param  list<string>  $calls  every call actually recorded, each already rendered as
     *                               "method(args)" — unlike otherObservedCalls elsewhere, the
     *                               method name varies per entry here, so it can't be factored
     *                               out to a single fixed $method the way those are
     */
    public function __construct(
        public readonly string $label,
        public readonly array $calls,
        public readonly bool $fabricated = false,
    ) {
        parent::__construct(self::renderMessage($label, $calls, $fabricated));
    }

    /**
     * @param  list<string>  $calls
     */
    public static function renderMessage(string $label, array $calls, bool $fabricated): string
    {
        $message = sprintf(
            'Test double `%s` expected no calls at all, but received: %s.',
            $label,
            CallListFormatter::describeCalls($calls),
        );

        return TestDoubleException::appendFabricatedNote($message, $fabricated);
    }
}
