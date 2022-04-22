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
     * @return Closure
     */
    public function getKeyHasher(): Closure
    {
        return static fn($k) => $k;
    }
}
