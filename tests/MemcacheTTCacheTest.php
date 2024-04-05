<?php

namespace Square\TTCache;

use Memcached;
use Square\TTCache\Store\ShardedMemcachedStore;

class MemcacheTTCacheTest extends TTCacheTest
{
    protected Memcached $mc;

    public function getTTCache(): TTCache
    {
        if (! isset($this->mc)) {
            $this->mc = new Memcached;
            $this->mc->addServers([['memcached', 11211]]);
            $this->mc->flush();
        }

        $store = new ShardedMemcachedStore($this->mc);
        $store->setShardingKey('hello');

        return new TTCache($store, $this->keyhasher ?? fn ($a) => $a);
    }

    public function getTTCacheWithKeyTracker(): TTCache
    {
        if (! isset($this->mc)) {
            $this->mc = new Memcached;
            $this->mc->addServers([['memcached', 11211]]);
            $this->mc->flush();
        }

        $store = new ShardedMemcachedStore($this->mc);
        $store->setShardingKey('hello');

        $this->keyTracker = new KeyTracker($store);

        return new TTCache($this->keyTracker, fn ($a) => $a);
    }

    public function getBogusTTCache(): TTCache
    {
        $this->mc = new Memcached;
        $store = new ShardedMemcachedStore($this->mc);
        $store->setShardingKey('hello');

        return new TTCache($store, fn ($a) => $a);
    }

    public function tearDown(): void
    {
        $this->mc->flush();
    }
}
