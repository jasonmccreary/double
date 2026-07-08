<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Fixtures;

interface IntersectionReturnInterface
{
    public function make(): Fillable&Sized;
}
