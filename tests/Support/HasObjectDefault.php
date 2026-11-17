<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Support;

class HasObjectDefault
{
    public function value($default = new NewInInitializerDefault): mixed
    {
        return $default;
    }
}
