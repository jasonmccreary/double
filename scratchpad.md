# Goals
- **Better Exception Messages** - Instead of a dense, technical error messages, a more human-readible "suggestive" message.
- **Too technical** - Instead of mock/spy/stub/partial/fake and expects versus shouldRecieve, `double()`
- **Not easy to contribute** - legacy (PHP 8.1), clear separation, slow-moving
- **Better docs** - like Laravel, prose.


```php
Mockery::mock(Foo::class)->expects('bar')->with('baz')->andReturns('quix')

// test
TestDouble::for(Foo::class);

// code
Foo::bar('baz');
```
