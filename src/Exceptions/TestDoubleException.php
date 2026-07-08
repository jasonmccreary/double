<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

/**
 * M1 placeholder shape: a plain runtime exception with a fully rendered
 * message string. ARCHITECTURE.md's design has this constructor take a
 * Diagnostic + DiagnosticRenderer instead, once the Diagnostics module
 * (M3) exists — that refactor is deliberately deferred, not done here.
 */
abstract class TestDoubleException extends \RuntimeException {}
