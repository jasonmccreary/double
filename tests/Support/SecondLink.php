<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Support;

interface SecondLink
{
    public function toThird(): ThirdLink;
}
