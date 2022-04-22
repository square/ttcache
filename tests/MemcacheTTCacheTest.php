<?php

namespace Square\TTCache;

use Closure;
use Memcached;
use Psr\SimpleCache\CacheInterface;
use Square\TTCache\Store\ShardedMemcachedStore;

class MemcacheTTCacheTest extends TTCacheTest
{
    /**
     * @var Memcached
     */
    protected Memcached $mc;

    public function getTTCache(): TTCache
    {
        if (!isset($this->mc)) {
            $this->mc = new Memcached;
            $this->mc->addServers([['memcached', 11211]]);
            $this->mc->flush();
        }

        $store = new ShardedMemcachedStore($this->mc);
        $store->setShardingKey('hello');
        return new TTCache($store);
    }

    public function tearDown(): void
    {
        $this->mc->flush();
    }

    /**
     * @test
     */
    function retrieving_parts_of_a_collection_still_applies_all_tags()
    {
        $coll = [
            new BlogPost('abc', 'Learn PHP the curved way', '...'),
            new BlogPost('def', 'Learn Python the curved way', '...'),
            new BlogPost('ghi', 'Learn Javascript the curved way', '...'),
            new BlogPost('klm', 'Learn Rust the curved way', '...'),
            new BlogPost('nop', 'Learn Go the curved way', '...'),
        ];

        $store = new class($this->mc) extends ShardedMemcachedStore {
            public $requestedKeys = [];
            public function __construct($mc)
            {
                parent::__construct($mc);
            }

            public function get($key, $default = null)
            {
                $this->requestedKeys[] = $key;
                return parent::get($key);
            }
        };
        $store->setShardingKey('hello');

        $this->tt = new TTCache($store);

        $built = fn() => $this->tt->remember('full-collection', null, [], function () use ($coll) {
            $posts = [];
            $keys = [];
            foreach ($coll as $post) {
                $keys[] = __CLASS__.':blog-collection:'.$post->id;
            }
            $this->tt->load($keys);

            foreach ($coll as $post) {
                $key = __CLASS__.':blog-collection:'.$post->id;
                $posts[] = $this->tt->remember($key, null, ['post:'.$post->id], fn () => "<h1>$post->title</h1><hr /><div>$post->content</div>");
            }

            return $posts;
        });


        $this->assertEquals([
            "<h1>Learn PHP the curved way</h1><hr /><div>...</div>",
            "<h1>Learn Python the curved way</h1><hr /><div>...</div>",
            "<h1>Learn Javascript the curved way</h1><hr /><div>...</div>",
            "<h1>Learn Rust the curved way</h1><hr /><div>...</div>",
            "<h1>Learn Go the curved way</h1><hr /><div>...</div>",
        ], $built());
        $this->assertEquals([
            "k:full-collection",
            "k:Square\TTCache\MemcacheTTCacheTest:blog-collection:abc",
            "k:Square\TTCache\MemcacheTTCacheTest:blog-collection:def",
            "k:Square\TTCache\MemcacheTTCacheTest:blog-collection:ghi",
            "k:Square\TTCache\MemcacheTTCacheTest:blog-collection:klm",
            "k:Square\TTCache\MemcacheTTCacheTest:blog-collection:nop",
        ], $store->requestedKeys);

        // When we call `built()` again, all the data should be pre-loaded and therefore come without talking to MC
        $store->requestedKeys = [];
        $built();
        $this->assertEquals([
            "k:full-collection",
        ], $store->requestedKeys);

        // Clear tag for "abc" and change the title for "abc"
        $this->tt->clearTags('post:'.$coll[0]->id);
        $store->requestedKeys = [];
        $coll[0]->title = 'Learn PHP the straight way';
        $this->assertEquals([
            "<h1>Learn PHP the straight way</h1><hr /><div>...</div>",
            "<h1>Learn Python the curved way</h1><hr /><div>...</div>",
            "<h1>Learn Javascript the curved way</h1><hr /><div>...</div>",
            "<h1>Learn Rust the curved way</h1><hr /><div>...</div>",
            "<h1>Learn Go the curved way</h1><hr /><div>...</div>",
        ], $built());
        $this->assertEquals([
            "k:full-collection",
            "k:Square\TTCache\MemcacheTTCacheTest:blog-collection:abc",
        ], $store->requestedKeys);

        // Newly cached value still contains all the tags. So clearing by another tag will also work.
        $this->tt->clearTags('post:'.$coll[1]->id);
        $store->requestedKeys = [];
        $coll[1]->title = 'Learn Python the straight way';
        $this->assertEquals([
            "<h1>Learn PHP the straight way</h1><hr /><div>...</div>",
            "<h1>Learn Python the straight way</h1><hr /><div>...</div>",
            "<h1>Learn Javascript the curved way</h1><hr /><div>...</div>",
            "<h1>Learn Rust the curved way</h1><hr /><div>...</div>",
            "<h1>Learn Go the curved way</h1><hr /><div>...</div>",
        ], $built());
        $this->assertEquals([
            "k:full-collection",
            "k:Square\TTCache\MemcacheTTCacheTest:blog-collection:def",
        ], $store->requestedKeys);
    }

    /**
     * @return Closure
     */
    public function getKeyHasher(): Closure
    {
        return static fn($k) => $k;
    }
}
