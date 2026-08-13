In reviewing the recent exception messages I noticed two things. This relates to a double that was implicitly marked "strict" due to `expects` being set for a method that was called.

First, the test is declared risky. We should avoid this by creating a simple assertion. It's noisy and does not seem Mockery does this for the equivalent test.

Second, the working "How it compares to the configured expecation" does not really match the human wording we have for other expectations. Maybe something more like, "There was another similar call to `recordView`: ..." This would align the messages more closely to the output of the "verifies" exception messages.

For context, here is the PHPUnit output that displays the current failure message and the risky test warning.

---


There was 1 failure:

1) JMac\Examples\Double\Tests\DoubleTest::testLookupFindsAndRecordsAView
Double `BookRepository` received a call to `recordView(JMac\Examples\Double\Book)` that doesn't match any of its configured expectations. `expects()` requires every call to match one exactly.

Here's how it compares to the configured expectation for `recordView`:
  book:
    - NULL
    + JMac\Examples\Double\Book

/Users/jasonmccreary/workspace/double-example/vendor/jasonmccreary/double/src/Engine/ExceptionFactory.php:101
/Users/jasonmccreary/workspace/double-example/vendor/jasonmccreary/double/src/Engine/ProxyBehavior.php:232
/Users/jasonmccreary/workspace/double-example/vendor/jasonmccreary/double/src/Engine/ProxyBehavior.php:40
/Users/jasonmccreary/workspace/double-example/src/CatalogService.php:19
/Users/jasonmccreary/workspace/double-example/tests/DoubleTest.php:28

--

There was 1 risky test:

1) JMac\Examples\Double\Tests\DoubleTest::testLookupFindsAndRecordsAView
This test did not perform any assertions

/Users/jasonmccreary/workspace/double-example/tests/DoubleTest.php:18
