# 1.x => 2.0.0

Version 2 introduces wrapped returns. Calls to `->remember` no longer directly return the value that was computed or retrieved from the cache but a `Result` object that wraps the value.

The `Result` object contains the value, the tags applied to it, and whether or not this was a cache `hit` vs. the value being re-computed.

## Use `->value()` where you used `->remember()`

Wherever you were using `->remember()` and directly retrieved the value, you now need to call `->remember()->value()`.

Note: if this was a call where you were using the `GetTaggedValue` directive, the `->value()` and `->tags()` methods will work the same.

## Remove uses of `GetTaggedValue`

Now that we are using wrapped returns via the `Result` class, using `GetTaggedValue` directive to access the tags is no longer necessary and calls should be replaced with standard returns:

```php
$result = $ttc->remember($key, $ttl, function () {
    return new GetTaggedValue('complex value');
});

$result->tags();
$result->value();
```

becomes:

```php
$result = $ttc->remember($key, $ttl, function () {
    return 'complex value';
});

$result->tags();
$result->value();
```
