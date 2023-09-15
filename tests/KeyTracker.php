<?php

namespace Square\TTCache;

use Psr\SimpleCache\CacheInterface;

class KeyTracker implements CacheInterface
{
    /**
     * @var array
     */
    public $requestedKeys = [];

    /**
     * @var CacheInterface
     */
    private CacheInterface $inner;

    public function __construct(CacheInterface $inner)
    {
        $this->inner = $inner;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->requestedKeys[] = $key;
        return $this->inner->get($key, $default);
    }

    public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
    {
        return $this->inner->set($key, $value, $ttl);
    }

    public function delete(string $key): bool
    {
        return $this->inner->delete($key);
    }

    public function clear(): bool
    {
        return $this->inner->clear();
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        return $this->inner->getMultiple($keys);
    }

    public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool
    {
        return $this->inner->setMultiple($values, $ttl);
    }

    public function deleteMultiple(iterable $keys): bool
    {
        return $this->inner->deleteMultiple($keys);
    }

    public function has(string $key): bool
    {
        return $this->inner->has($key);
    }
}
