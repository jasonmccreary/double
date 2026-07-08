<?php

declare(strict_types=1);

namespace TestDouble\Engine;

/**
 * See ARCHITECTURE.md, "Modes: Loose, Strict, Passthru." A double has
 * exactly one mode, set once at setup time and immutable after.
 *
 * M1 implements Strict only. Loose and Passthru exist here as named cases
 * so the rest of the engine can reason about mode as a closed set, but
 * neither has a working fallback path yet — DoubleControlMethods::passthru()
 * throws rather than setting Mode::Passthru, and there is deliberately no
 * ->loose() method (Loose is only reachable as the M4 default, never
 * requested explicitly). See DoubleState::mode() for the M1 stand-in
 * default.
 */
enum Mode
{
    case Loose;
    case Strict;
    case Passthru;
}
