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
            $diagnostic instanceof UnknownMethodDiagnostic => $this->renderUnknownMethod($diagnostic),
            $diagnostic instanceof ModeConfigurationDiagnostic => $this->renderModeConfiguration($diagnostic),
            $diagnostic instanceof InvalidDoubleTargetDiagnostic => $this->renderInvalidDoubleTarget($diagnostic),
            $diagnostic instanceof PassthruAutoInstantiationDiagnostic => $this->renderPassthruAutoInstantiation($diagnostic),
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
            .'matches this call, and the double is in Strict mode.%s',
            $diagnostic->method,
            $diagnostic->argumentsDescription,
            $diagnostic->label,
            $this->fabricatedNote($diagnostic->fabricated),
        );
    }

    private function renderUnsatisfiedExpectations(UnsatisfiedExpectationsDiagnostic $diagnostic): string
    {
        $blocks = array_map($this->renderOneUnsatisfiedExpectation(...), $diagnostic->expectations);

        $message = sprintf(
            "%d expectation(s) were not satisfied on test double \"%s\":\n\n%s",
            count($diagnostic->expectations),
            $diagnostic->label,
            implode("\n\n", $blocks),
        );

        if ($diagnostic->fabricated) {
            $message .= "\n\n".trim($this->fabricatedNote(true));
        }

        return $message;
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
            .'allows at most %d call(s).%s',
            $diagnostic->label,
            $diagnostic->callNumber,
            $diagnostic->method,
            $diagnostic->argumentsDescription,
            $diagnostic->maximum,
            $this->fabricatedNote($diagnostic->fabricated),
        );
    }

    private function renderUnknownMethod(UnknownMethodDiagnostic $diagnostic): string
    {
        return sprintf(
            'Cannot configure "%s" on a test double of "%s": no such method is declared there.%s',
            $diagnostic->method,
            $diagnostic->target,
            $this->fabricatedNote($diagnostic->fabricated),
        );
    }

    private function renderModeConfiguration(ModeConfigurationDiagnostic $diagnostic): string
    {
        return sprintf(
            'Test double "%s" already has its mode set to %s; cannot also set it to %s. '
            .'A double\'s mode is set once, at setup time, and is immutable after that.%s',
            $diagnostic->label,
            $diagnostic->current,
            $diagnostic->attempted,
            $this->fabricatedNote($diagnostic->fabricated),
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

    private function renderPassthruAutoInstantiation(PassthruAutoInstantiationDiagnostic $diagnostic): string
    {
        return sprintf(
            'Cannot auto-instantiate a real "%s" to passthru to: %s. '
            .'Pass an existing instance instead: ->passthru($existingInstance).',
            $diagnostic->target,
            $diagnostic->reason,
        );
    }

    /**
     * Provenance tagging for fabricated stand-in doubles (see ARCHITECTURE.md,
     * "Modes: Loose, Strict, Passthru" — "mandatory provenance tagging on
     * every fabricated object"). Returns '' when not fabricated so every
     * call site can unconditionally splice this into its sprintf.
     */
    private function fabricatedNote(bool $fabricated): string
    {
        if (! $fabricated) {
            return '';
        }

        return ' This double was auto-fabricated as a safe-default stand-in by Loose mode, '
            .'not created directly via TestDouble::for() — see ARCHITECTURE.md\'s '
            .'"Modes: Loose, Strict, Passthru."';
    }
}
