# Installation

You may install this library as a development dependency with Composer:

```sh
composer require --dev jasonmccreary/test-double
```

## Requirements

This library needs PHP 8.3 or newer, and nothing else. There's no required dependency on PHPUnit or any other test runner — the core library doesn't know or care what's running your tests.

## PHPUnit Integration

If PHPUnit happens to be installed in your project, this library notices and improves a couple of things automatically:

- Failures are reported as PHPUnit failures rather than generic errors, so they're attributed to the right test.
- You may add a trait to your base test case to have every expectation verified automatically, without calling `->verify()` yourself.

None of this requires configuration — it's detected the moment your tests run. The full details live in [PHPUnit Integration](08-phpunit-integration.md). If you're using another test runner, or none at all, everything still works the same way; you just call `$double->verify()` yourself, as shown in [Verification](06-verification.md).

## Confirming It Works

```php
<?php

use JMac\Testing\TestDouble;

require 'vendor/autoload.php';

$double = TestDouble::for(Countable::class);
$double->allows('count')->returns(3);

var_dump($double->count()); // int(3)
```

If that prints `int(3)`, you're ready to go. Head over to [Creating Test Doubles](03-creating-test-doubles.md) to see what a real one looks like.
