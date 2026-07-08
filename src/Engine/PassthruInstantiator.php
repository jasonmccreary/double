<?php

declare(strict_types=1);

namespace JMac\Testing\Engine;

use JMac\Testing\Exceptions\PassthruAutoInstantiationException;

/**
 * @internal
 *
 * Backs ->passthru() called with no argument. See ARCHITECTURE.md,
 * "Passthru" — "attempts reflection-based auto-instantiation, throwing a
 * clear setup-time error suggesting ->passthru($existingInstance) if that
 * fails. Only valid for classes, not interfaces."
 */
final class PassthruInstantiator
{
    public static function autoInstantiate(string $target): object
    {
        $reflection = new \ReflectionClass($target);

        if ($reflection->isInterface()) {
            throw PassthruAutoInstantiationException::isInterface($target);
        }

        try {
            return $reflection->newInstance();
        } catch (\Throwable $exception) {
            throw PassthruAutoInstantiationException::constructionFailed($target, $exception);
        }
    }
}
