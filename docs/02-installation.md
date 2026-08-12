# Installation

You may install this library as a development dependency with Composer:

```sh
composer require --dev jasonmccreary/double
```

## Requirements

This library needs PHP 8.3 or newer, and nothing else. There's no required dependency on PHPUnit or any other test runner. The core library doesn't know or care what's running your tests.

## Test Suite Integration

If PHPUnit happens to be installed in your project, this library notices and improves a couple of things automatically — Pest included, since Pest tests run on real PHPUnit `TestCase` instances under the hood:

- Failures are reported as PHPUnit failures rather than generic errors, so they're attributed to the right test.
- You may add a trait to your suite to have every expectation verified automatically, without calling `->verify()` yourself. For PHPUnit that means your base test case; for Pest, `uses()`.

None of this requires configuration. It's detected the moment your tests run. The full details live in [Test Suite Integration](08-test-suite-integration.md). If you're using another test runner, or none at all, everything still works the same way; you just call `$double->verify()` yourself, as shown in [Verification](06-verification.md).

## Confirming It Works

```php
<?php

use JMac\Testing\Double;

require 'vendor/autoload.php';

$double = Double::for(Countable::class);
$double->allows('count')->returns(3);

var_dump($double->count()); // int(3)
```

If that prints `int(3)`, you're ready to go. Head over to [Creating Doubles](03-creating-doubles.md) to see what a real one looks like.
