<?php

namespace Square\TTCache;

use Cache\Adapter\Redis\RedisCachePool;
use Closure;
use Illuminate\Cache\RedisStore;
use Psr\SimpleCache\CacheInterface;
use Redis;

class RedisTTCacheTest extends TTCacheTest
{
    /**
     * @var Redis
     */
    private Redis $redis;

    protected $keySeparator = '|';

    public function getTTCache(): TTCache
    {
        $this->redis = new Redis();
        $this->redis->connect('redis');
        $store = new RedisCachePool($this->redis);
        return new TTCache($store, $this->getKeyHasher(), $this->keySeparator);
    }

    /**
     * @return Closure
     */
    public function getKeyHasher(): Closure
    {
        return function ($k) {
            return str_replace(':', '|', $k);
        };
    }

    public function tearDown(): void
    {
        $this->redis->flushAll();
    }
}

