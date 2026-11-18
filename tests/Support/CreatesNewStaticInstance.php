<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Support;

/**
 * fresh() creates an instance with new static whose constructor calls an
 * overridable method, like Illuminate\Database\Eloquent\Model::newInstance().
 * make() does the same from a static factory.
 */
class CreatesNewStaticInstance
{
    private string $name;

    public function __construct(string $name)
    {
        $this->initialize($name);
    }

    protected function initialize(string $name): void
    {
        $this->name = $name;
    }

    public static function make(string $name): static
    {
        return new static($name);
    }

    public function fresh(string $name): static
    {
        return new static($name);
    }

    public function name(): string
    {
        return $this->name;
    }
}
