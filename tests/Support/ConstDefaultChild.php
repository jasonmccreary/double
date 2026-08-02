<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Support;

/**
 * The actual double target — inherits ConstDefaultBase's "self"/"parent"
 * -qualified constant-defaulted methods without overriding them, exactly as
 * Illuminate\Console\OutputStyle::writeln() reaches its "self::OUTPUT_NORMAL"
 * default via inheritance from Symfony's parent OutputStyle.
 */
class ConstDefaultChild extends ConstDefaultBase {}
