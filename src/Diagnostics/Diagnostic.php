<?php

declare(strict_types=1);

namespace JMac\Testing\Diagnostics;

/**
 * Marker interface for everything a DiagnosticRenderer can render. See
 * ARCHITECTURE.md, "Module boundaries" — Diagnostics has zero dependencies
 * on the rest of the library, so every implementation holds plain strings
 * and scalars only, never a Matcher reference.
 */
interface Diagnostic {}
