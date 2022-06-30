# Tag Tree Cache

[![PHP](https://github.com/square/ttcache/actions/workflows/php.yml/badge.svg)](https://github.com/square/ttcache/actions/workflows/php.yml)

`TTCache` or Tag Tree Cache is a cache implementation that builds a recursive tree of tags and applies them to the values being cached.
This allows recursive caching where clearing a value cached deep inside the tree also clears any cached value that depended on it.

This is useful when generating recursive datastructures such as `json` documents, `html` documents or `xml` documents for example.

# Installation

`composer require square/ttcache:^2.0`

# Integration

## Laravel

In Laravel, you need to make sure you create `TTCache` as a singleton if you intend to use the DI container to access it.
In your service provider:

```php
$this->app->singleton(TTCache::class);
```

# Context

Say you're trying to render a `json` object but parts of the result come from expensive calculations. Let's take this structure as an example:

```json
{
    "id": "b5d7c58d-63c6-443e-8144-6b9cab8aceb6",
    "name": "Shoe Shine",
    "inventory": {
        "store-1": {
            "shipping": 9,
            "pickup": 9
        },
        "store-2": {
            "...": "..."
        }
    },
    "price": 900
}
```
Where the inventory and prices might be fairly expensive to compute.

The code to generate this result could look something like this:
```php
class ProductController
{
    public function show($productId)
    {
        $p = Product::find($productId);
        $stores = Store::all();

        return [
            'id' => $p->id,
            'name' => $p->name,
            'inventory' => $this->inventory($p, $stores),
            'price' => $this->price($p)
        ]
    }

    public function inventory($product, $stores)
    {
        foreach ($stores as $store) {
            yield $store->id => $this->singleStoreInventory($product, $store);
        }
    }

    public function singleStoreInventory($product, $store)
    {
        // expensive computation
    }

    public function price($product)
    {
        // expensive computation
    }
}
```

The result of computing the price depends only on data from `$product`, the inventory info depends on both `$store` and `$product`.

`TTCache` allows you to cache multiple pieces of the computation, as well as cache the whole result and only invalidate the parts that should be invalidated when needed.

## Simple use of tags

So the same code could be re-written as follows:

```php
public function show($productId)
{
    $p = Product::find($productId);
    $stores = Store::all();

    return $this->ttCache->remember('show:cachekey:'.$productId, tags: ['products:'.$productId, 'stores:'.$stores[0]->id, 'stores:'.$stores[1]->id], fn () => [
        'id' => $p->id,
        'name' => $p->name,
        'inventory' => $this->inventory($p, $stores),
        'price' => $this->price($p)
    ])->value();
}
```

The cached value would have the tags `products:1, stores:1, stores:2`. And clearing any of those tags would clear the `show:cachekey:1` tag value, forcing us to recompute the results.

You would do this the following way:

```php
$p->save();
$ttCache->clearTags(['products:'.$p->id]);
```

This is all fine and well, but we can do better.

## A tree of tags

`TTCache` allows to cache intermediate results as part of building up a parent value. In this case, we would simplify our `show` function to:

```php
public function show($productId)
{
    $p = Product::find($productId);
    $stores = Store::all();

    return $this->ttCache->remember('show:cachekey:'.$productId, tags: ['products:'.$productId], fn () => [
        'id' => $p->id,
        'name' => $p->name,
        'inventory' => $this->inventory($p, $stores),
        'price' => $this->price($p)
    ])->value();
}
```

We have removed the store based tags since the store based info isn't used directly in this part of the code but by other methods. We'll now update those methods:


```php
public function singleStoreInventory($product, $store)
{
    $this->ttCache->remember(__METHOD__.':'.$product->id.':'.$store->id, tags: ['product:'.$product->id, 'store:'.$store->id], function () {
        // expensive computation
    })->value();
}

public function price($product)
{
    $this->ttCache->remember(__METHOD__.':'.$product->id, tags: ['product:'.$product->id], function () {
        // expensive computation
    })->value();
}
```

Now if one of the store's inventory got updated, we would clear any tag related to that store:

```php
$store->save();
$ttCache->clearTags('store:'.$store->id);
```

Doing so would no remove the cached computation for `price()` nor would it remove the cached inventory computation for the other store.
But it would clear the main cached value in `show()` with the key `show:cachekey:1`. It would remove it even though that cached value
wasn't directly tagged with the `store:1` tag. The tag tree is getting built for you during the successive nested calls to `remember`.

## One more level

If we keep in mind the previous code, we could bring this one level higher in a middleware that would cache the results based only on the url.
Not yet knowing at all how the response would be generated or which data and tags would participate in building this response.

```php
class CachingMiddleware
{
    public function handle($request, $next)
    {
        $url = $request->url();
        $cachekey = sha1($url);
        return $this->ttCache->remember($cachekey, fn () => $next($request))->value();
    }
}
```

This layer of the cache has no idea what tags will end up being used to generate the response. However when this calls our sample code from above,
it would end up being tagged with `product:1, store:1, store:2` and clearing any of those tags would end up clearing the response that is cached
directly based on the URL.

## Caching result information

Sometimes it can be useful to know if the value was or wasn't retrieved from cache. This can be used for telemetry to validate how often you get a cache hit / miss.
Sometimes it's useful to get the tags that were applied to a value before returning it. This can be used when your app is behind a CDN that supports `Surrogate-Keys` and you want to use the tags as the `Surrogate-Keys` so the CDN can cache the response and you can properly invalidate it.

```php
public function show($productId)
{
    $p = Product::find($productId);
    $stores = Store::all();

    $cacheResult = $this->ttCache->remember('show:cachekey:'.$productId, tags: ['products:'.$productId, 'stores:'.$stores[0]->id, 'stores:'.$stores[1]->id], fn () => [
        'id' => $p->id,
        'name' => $p->name,
        'inventory' => $this->inventory($p, $stores),
        'price' => $this->price($p)
    ]);

    if ($cacheResult->isMiss()) {
        $this->trackCacheMiss();
    }

    $response = new Response($cacheResult->value());
    $response->header('Surrogate-Keys', join(',', $cacheResult->tags()));

    return $response;
}
```

## Cache Errors

When a cache throws an Exception, `TTCache` will swallow it and move on to computing the result from code instead.
Howver, the `Result` will carry over the exception information and the fact that there was an error so you can properly monitor,
track or log those instances.

```php
public function show($productId)
{
    $cacheResult = $this->ttCache->remember('cachekey', tags: [], fn () => 'computed value');

    if ($cacheResult->hasError()) {
        \Log::error('caching error', ['error' => $cacheResult->error()]);
    }
}
```

## Dealing with collections

Sometimes when you work on a collection of items and cache the results of applying a function to those, you'll have only a few of those items that are out of cache.
Going strictly with `->remember` calls, this would mean that a collection of 200 items where 2 are out of cache, would still need to hit the cache 198 times to retrieve the other cached values. Depending on the size of the collection at hand, this can be acceptable or a performance hog. For cases where it becomes a performance hog, ttcache provides the `->load($keys)` method which allows to pre-load a whole set of values that can then be retrieved from memory without an expensive trip to a distributed cache.

```php
$collection = BlogPosts::all();
$t->remember('full-collection', 0, [], function () use ($collection) {
    $posts = [];
    $keys = [];

    // Create an array all the caching keys for items in the collection
    // This is actually a map of `entity_id` => `cache_key`
    foreach ($collection as $post) {
        $keys[$post->id] = __CLASS__.':blog-collection:'.$post->id;
    }
    // Pre-load them into memory
    $this->tt->load($keys);

    // Run through the collection as usual, making calls to `->remember` that will either resolve in memory
    // Or will set a new value in cache for those items that couldn't be resolved in memory
    foreach ($collection as $post) {
        $key = __CLASS__.':blog-collection:'.$post->id;
        $posts[] = $this->tt->remember($key, 0, ['post:'.$post->id], fn () => "<h1>$post->title</h1><hr /><div>$post->content</div>")->value();
    }

    return $posts;
})->value();
```

### Advanced

To further improve performance, you might want to know which keys were not loaded and from there be able to load all the required entities in a single call.
The `load` method returns a `LoadResult` object which lets you know what was or wasn't successfully found in the cache.

```php
$postIds = [1, 2, 3, 4, 5, 6];
$t->remember('full-collection', 0, [], function () use ($postIds) {
    $posts = [];
    $keys = [];

    // Create an array all the caching keys for items in the collection
    // This is actually a map of `entity_id` => `cache_key`
    foreach ($postIds as $postId) {
        $keys[$postId] = __CLASS__.':blog-collection:'.$postId;
    }
    // Pre-load them into memory
    $loadResult = $this->tt->load($keys);
    // Since we passed our keys to `load` as a map of `entity_id` => `cache_key`, we can retrieve the entity ids here.
    $missing = BlogPosts::whereIn('id', array_keys($loadResult->missingKeys()));

    // Run through the collection as usual, making calls to `->remember` that will either resolve in memory
    // Or will set a new value in cache for those items that couldn't be resolved in memory
    foreach ($postIds as $postId) {
        $key = __CLASS__.':blog-collection:'.$post->id;
        $posts[] = $this->tt->remember($key, 0, ['post:'.$post->id], fn () => "<h1>{$missing[$postId]->title}</h1><hr /><div>{$missing[$postId]->content}</div>")->value();
    }

    return $posts;
})->value();
```

## Bypassing the cache for some results

Some results should be kept out of the cache. For example a middleware that caches full HTTP responses would by default cache error responses as well.
If the error came because of a transient connection error to some other service, we don't want to cache that type of result.
For this, you can wrap your return in a `BypassCache` `ReturnDirective`.

```php
class CachingMiddleware
{
    public function handle($request, $next)
    {
        $url = $request->url();
        $cachekey = sha1($url);
        return $this->ttCache->remember($cachekey, function () use ($next, $request) {
            $response = $next($request);
            if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
                return $response;
            }

            return new BypassCache($response);
        });
    }
}
```

Doing so would guarantee that error responses never end up in cache.

## Heritable Tags (~ global tags)

Sometimes you want a whole hierarchy of tagged cache values to share a common tag. In a SaaS application for example, you might want to tag all of an account's cached values with the account's ID so that if an issue arises, you can easily clear their entire set of cached values, ensuring a fresh start.

This is possible via the use of `HeritableTags`. With heritable tags, you can transform this code where the tag has to be applied at every level:

```php
$tt->remember('key:level:1', 0, ['account:123'], function () {
    //... code ...
    $tt->remember('key:level:2', 0, ['account:123'], function () {
        // ... more ... code ...
        $tt->remember('key:level:3', 0, ['account:123'], function () {
            // ... even ... more ... code ...
            $tt->remember('key:level:4', 0, ['account:123'], function () {
                // ... wow ... that's ... a ... lot ... of ... code ...
            });
        });
    });
});
```

To this:

```php
$tt->remember('key:level:1', 0, [new HeritableTag('account:123')], function () {
    //... code ...
    $tt->remember('key:level:2', 0, [], function () {
        // ... more ... code ...
        $tt->remember('key:level:3', 0, [], function () {
            // ... even ... more ... code ...
            $tt->remember('key:level:4', 0, [], function () {
                // ... wow ... that's ... a ... lot ... of ... code ...
            });
        });
    });
});
```

Now the tag only needs to be applied once and will be automatically added to any child cached value at any level.

## Clearing the whole cache

Sometimes, you might want or need to clear every cached value. For example you are changing the format or the code that returns a nested cache value. Those cache values are deeply nested and there's millions of them, making it hard to generate and clear every single tag for those.

Let's explore options around clearing a large part of the cache:

### Global tag (Not Recommended)

While this approach is not recommended, it helps understand the recommended approach.

A simple approach would be to add a `HeritableTag` at the root that would be a cache version. For example, the very first call to `remember` could use:

```php
$tt->remember('key', 0, [new HeritableTag('cache-global')], function () {
    // ... code ...
});
```

Then `cache-global` would be applied to every single value on your cache and calling

```php
$tt->clearTags('cache-global');
```

Would invalidate every single cached value.

**Why is it not recommended**

Depending on your situation, clearing the entirety of your cache in a split second like this could have dramatic effects. If your code isn't able to sustain the volume of codes it is receiving without being backed by cache, then your service would be down, or your library unresponsive, or your process would spike CPU usage beyond reason etc...

Having experienced this type of situation first hand, we advise entirely against having such global tags.

You might not even be in control of when that cache gets cleared. If your distributed cache of choice needs to makes space for other values and decides to eliminate this specific tag from storage, then suddenly all your cache data is gone and your systems are in the red.

### ShardingTags

The approach we suggest instead is the use of sharding tags. If again, we take the example of a SaaS platform where the cache for each account has completely separate tags, maybe you are already adding a `HeritableTag` based on the account ID.

`ShardingTag` are `HeritableTag` that will hash a value and associate it to any shard within a given number of shards, creating a tag like `shard:1` or `shard:18`. You can then clear those shard tags one by one, allowing some time for your system caches to get warm again and avoid a catastrophic event.

```php
$tt->remember('key', 0, [new ShardingTag('shard', $account->id, 20)], function () {
    // ... code ...
});
```

Since a `ShardingTag` is a `HeritableTag`, this would ensure that any value cached within this call has the same tag applied. When you need to clear the entirety of your cache but go at it prudently, you can then:

```php
for ($i = 0; $i < 20; $i++) {
    $tt->clearTags('shard:'.$i);
    sleep(60);
}
```

This would clear 5% (1/20th) of your cache every minute.
