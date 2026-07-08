<?php

declare(strict_types=1);

namespace JMac\Testing\Diagnostics;

/**
 * The only DiagnosticRenderer implementation today. Renders every Diagnostic
 * type to the plain human-prose strings that back TestDoubleException::getMessage()
 * (see ARCHITECTURE.md, "Core: framework-agnostic").
 */
final class PlainTextRenderer implements DiagnosticRenderer
{
    public function render(Diagnostic $diagnostic): string
    {
        return match (true) {
            $diagnostic instanceof UnexpectedCallDiagnostic => $this->renderUnexpectedCall($diagnostic),
            $diagnostic instanceof UnsatisfiedExpectationsDiagnostic => $this->renderUnsatisfiedExpectations($diagnostic),
            $diagnostic instanceof CallLimitExceededDiagnostic => $this->renderCallLimitExceeded($diagnostic),
            $diagnostic instanceof UnconfiguredReturnDiagnostic => $this->renderUnconfiguredReturn($diagnostic),
            $diagnostic instanceof UnknownMethodDiagnostic => $this->renderUnknownMethod($diagnostic),
            $diagnostic instanceof ModeConfigurationDiagnostic => $this->renderModeConfiguration($diagnostic),
            $diagnostic instanceof InvalidDoubleTargetDiagnostic => $this->renderInvalidDoubleTarget($diagnostic),
            default => throw new \LogicException(sprintf(
                'No PlainTextRenderer support for diagnostic type "%s".',
                get_class($diagnostic),
            )),
        };
    }

    private function renderUnexpectedCall(UnexpectedCallDiagnostic $diagnostic): string
    {
        return sprintf(
            'Unexpected call to "%s(%s)" on test double "%s": no configured expects()/allows() '
            .'matches this call, and the double is in Strict mode.',
            $diagnostic->method,
            $diagnostic->argumentsDescription,
            $diagnostic->label,
        );
    }

    private function renderUnsatisfiedExpectations(UnsatisfiedExpectationsDiagnostic $diagnostic): string
    {
        $blocks = array_map($this->renderOneUnsatisfiedExpectation(...), $diagnostic->expectations);

        return sprintf(
            "%d expectation(s) were not satisfied on test double \"%s\":\n\n%s",
            count($diagnostic->expectations),
            $diagnostic->label,
            implode("\n\n", $blocks),
        );
    }

    private function renderOneUnsatisfiedExpectation(UnsatisfiedExpectation $expectation): string
    {
        $lines = ['    '.$expectation->description];

        if ($expectation->otherObservedCalls !== []) {
            $lines[] = '';
            $lines[] = sprintf(
                '    "%s" was called with different arguments elsewhere in this test:',
                $expectation->method,
            );
            $lines[] = '';

            foreach ($expectation->otherObservedCalls as $call) {
                $lines[] = sprintf('        %s(%s)', $expectation->method, $call);
            }
        }

        return implode("\n", $lines);
    }

    private function renderCallLimitExceeded(CallLimitExceededDiagnostic $diagnostic): string
    {
        return sprintf(
            'Test double "%s" received call #%d to "%s(%s)", but the matching expectation '
            .'allows at most %d call(s).',
            $diagnostic->label,
            $diagnostic->callNumber,
            $diagnostic->method,
            $diagnostic->argumentsDescription,
            $diagnostic->maximum,
        );
    }

    private function renderUnconfiguredReturn(UnconfiguredReturnDiagnostic $diagnostic): string
    {
        return sprintf(
            'Test double "%s" matched a call to "%s" that has no configured returns()/throws()/'
            .'returnsUsing(). Automatic safe-default return values are not implemented until M4 '
            .'(see ARCHITECTURE.md) — configure an explicit return for this expectation for now.',
            $diagnostic->label,
            $diagnostic->method,
        );
    }

    private function renderUnknownMethod(UnknownMethodDiagnostic $diagnostic): string
    {
        return sprintf(
            'Cannot configure "%s" on a test double of "%s": no such method is declared there.',
            $diagnostic->method,
            $diagnostic->target,
        );
    }

    private function renderModeConfiguration(ModeConfigurationDiagnostic $diagnostic): string
    {
        return sprintf(
            'Test double "%s" already has its mode set to %s; cannot also set it to %s. '
            .'A double\'s mode is set once, at setup time, and is immutable after that.',
            $diagnostic->label,
            $diagnostic->current,
            $diagnostic->attempted,
        );
    }

    private function renderInvalidDoubleTarget(InvalidDoubleTargetDiagnostic $diagnostic): string
    {
        return sprintf(
            'Cannot create a test double for "%s": %s.',
            $diagnostic->target,
            $diagnostic->reason,
        );
    }
}
