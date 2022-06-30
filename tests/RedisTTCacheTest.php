<?php

namespace Square\TTCache;

use Cache\Adapter\Redis\RedisCachePool;
use Redis;

class RedisTTCacheTest extends TTCacheTest
{
    /**
     * @var Redis
     */
    private Redis $redis;

    /**
     * @return TTCache
     */
    public function getTTCache(): TTCache
    {
        $this->redis = new Redis();
        $this->redis->connect('redis');
        return new TTCache(new RedisCachePool($this->redis));
    }

    public function getBogusTTCache(): TTCache
    {
        $this->redis = new Redis();
        $this->redis->connect('redis');
        $pool = new class($this->redis) extends RedisCachePool {
            public function crash()
            {
                $this->cache = new Redis();
            }
        };
        $tt = new TTCache($pool);
        $pool->crash();
        return $tt;
    }

    public function tearDown(): void
    {
        $this->redis->flushAll();
    }


}

