<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Support;

interface IntersectionReturnInterface
{
    public function make(): Fillable&Sized;

    public function makeLogger(): Sized&ConcreteLogger;
}
