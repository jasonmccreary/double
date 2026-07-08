<?php

declare(strict_types=1);

namespace JMac\Testing\Diagnostics;

/**
 * A single generic render() method rather than one method per Diagnostic
 * type: TestDoubleException's constructor renders whatever Diagnostic a
 * subclass hands it without knowing its concrete type, and this keeps the
 * contract open to new diagnostic types the same way Matcher stays closed
 * at three methods (see ARCHITECTURE.md, "Matcher").
 */
interface DiagnosticRenderer
{
    public function render(Diagnostic $diagnostic): string;
}
