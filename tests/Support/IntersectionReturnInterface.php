<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Support;

interface IntersectionReturnInterface
{
    public function make(): Fillable&Sized;

    public function makeLogger(): Sized&ConcreteLogger;

    public function makeFactory(): CreatesNewStaticInstance&Sized;

    public function makeProtectedSize(): ProtectedSize&Sized;

    public function makeTwoClasses(): ConcreteLogger&Book;

    public function makeDiskManager(): DiskManager&Sized;

    public function makeDiskResolver(): DiskManager&DiskResolverInterface;
}
