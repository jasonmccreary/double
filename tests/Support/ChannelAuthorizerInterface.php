<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Support;

interface ChannelAuthorizerInterface
{
    public function authorize(array $channels): bool;
}
