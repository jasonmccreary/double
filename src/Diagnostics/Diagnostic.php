<?php

declare(strict_types=1);

namespace JMac\Testing\Diagnostics;

/**
 * Marker interface for getDiagnostic()'s return type. Exceptions implement
 * it directly rather than wrapping a separate data object.
 */
interface Diagnostic {}
