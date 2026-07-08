<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

use JMac\Testing\Diagnostics\Diagnostic;
use JMac\Testing\Diagnostics\DiagnosticRenderer;
use JMac\Testing\Diagnostics\PlainTextRenderer;

/**
 * getMessage() gives full human prose with zero setup in any test runner —
 * the default PlainTextRenderer needs no configuration. getDiagnostic()
 * gives structured access for anything that wants it (e.g. the future
 * PHPUnit ComparisonFailure integration, see ARCHITECTURE.md's "PHPUnit
 * integration").
 */
abstract class TestDoubleException extends \RuntimeException
{
    public function __construct(
        private readonly Diagnostic $diagnostic,
        DiagnosticRenderer $renderer = new PlainTextRenderer,
    ) {
        parent::__construct($renderer->render($diagnostic));
    }

    public function getDiagnostic(): Diagnostic
    {
        return $this->diagnostic;
    }
}
