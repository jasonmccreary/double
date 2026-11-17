<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Support;

const NAMESPACED_DEFAULT_MODE = 42;

interface NamespacedConstantDefaultInterface
{
    public function withGlobalConstant(string $separator = PHP_EOL): void;

    public function withNamespacedConstant(int $mode = NAMESPACED_DEFAULT_MODE): void;
}
