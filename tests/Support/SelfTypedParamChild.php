<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Support;

/**
 * The actual double target — inherits SelfTypedParamBase's "self"/"parent"
 * -typed methods without overriding them, exactly as an Eloquent model
 * inherits Model::newPivot().
 */
class SelfTypedParamChild extends SelfTypedParamBase {}
