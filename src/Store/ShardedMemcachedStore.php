<?php

declare(strict_types=1);

namespace Square\TTCache\Store;

use Memcached;

/**
 * PSR Cache interface implementation of memcache that needs to receive a sharding key
 * and ensures all the values stored through this instance end up on the same MC server.
 */
class ShardedMemcachedStore implements CacheStoreInterface
{
    protected Memcached $mc;

    protected string $shardingKey;

    private const MC_SUCCESS = 0;

    private const MC_NOT_FOUND = 16;

    private const MC_VALID_CODES = [
        self::MC_SUCCESS,
        self::MC_NOT_FOUND,
    ];

    public function __construct(Memcached $mc)
    {
        $this->mc = $mc;
    }

    public function setShardingKey(string $key): void
    {
        $this->shardingKey = $key;
    }

    protected function checkResultCode(): void
    {
        if (! in_array($this->mc->getResultCode(), self::MC_VALID_CODES)) {
            throw new CacheStoreException('invalid MC return code', $this->mc->getResultCode());
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $result = $this->mc->getByKey($this->shardingKey, $key);
        $this->checkResultCode();

        return $result;
    }

    public function set(string $key, mixed $value, int|\DateInterval $ttl = null): bool
    {
        $result = $this->mc->setByKey($this->shardingKey, $key, $value, $ttl ?? 0);
        $this->checkResultCode();

        return $result;
    }

    public function delete(string $key): bool
    {
        $result = $this->mc->deleteByKey($this->shardingKey, $key);
        $this->checkResultCode();

        return $result;
    }

    public function clear(): bool
    {
        throw new CacheStoreException('not implemented on purpose');
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $result = $this->mc->getMultiByKey($this->shardingKey, $keys);
        $this->checkResultCode();

        return $result;
    }

    public function setMultiple(iterable $values, int|\DateInterval $ttl = null): bool
    {
        $result = $this->mc->setMultiByKey($this->shardingKey, $values, $ttl ?? 0);
        $this->checkResultCode();

        return $result;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        $result = $this->mc->deleteMultiByKey($this->shardingKey, $keys);
        $this->checkResultCode();

        return $result;
    }

    public function has(string $key): bool
    {
        throw new CacheStoreException('not implemented');
    }
}
