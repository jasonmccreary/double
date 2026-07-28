<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Support;

/**
 * Mirrors Illuminate\Database\Eloquent\Model::newPivot(self $parent, ...): a
 * base class declaring "self"/"parent"-typed parameters, inherited
 * unoverridden by a further subclass — the shape that must be doubled
 * without a fatal "Declaration ... must be compatible with ..." error.
 */
class SelfTypedParamBase extends SelfTypedParamGrandparent
{
    public function withSelf(self $sibling): string
    {
        return static::class;
    }

    public function withParent(parent $ancestor): string
    {
        return static::class;
    }
}
