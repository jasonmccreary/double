<?php

declare(strict_types=1);

namespace JMac\Testing\Diagnostics;

/**
 * Marker interface for getDiagnostic()'s return type. Every
 * TestDoubleException implements this directly (it holds its own diagnostic
 * fields and renders its own message) rather than wrapping a separate data
 * object. The interface still lives here, dependency-free, so a future
 * non-exception diagnostic producer isn't ruled out.
 */
interface Diagnostic {}
