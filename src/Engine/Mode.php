<?php

declare(strict_types=1);

namespace JMac\Testing\Engine;

/**
 * A double has exactly one mode (Loose, Strict, Passthru), set once at
 * setup time and immutable after.
 *
 * There is deliberately no ->loose() method — Loose is only ever reached as
 * DoubleState::mode()'s default, never requested explicitly.
 */
enum Mode
{
    case Loose;
    case Strict;
    case Passthru;
}
