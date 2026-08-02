<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Support;

/**
 * Mirrors Illuminate\Http\Resources\ConditionallyLoadsAttributes::when(),
 * whose $default = new MissingValue signature crashed ClassGenerator: PHP
 * 8.1+ "new in initializers" lets a default value be an arbitrary `new
 * ClassName(...)` expression, which ReflectionParameter::getDefaultValue()
 * evaluates into a real object with no __set_state() — not reproducible as
 * a literal via var_export().
 */
interface NewInInitializerParamInterface
{
    public function untyped($condition, $default = new NewInInitializerDefault): mixed;

    public function typed(NewInInitializerDefault $default = new NewInInitializerDefault): NewInInitializerDefault;
}
