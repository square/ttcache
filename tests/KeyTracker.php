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

    public function get($key, $default = null)
    {
        $this->requestedKeys[] = $key;
        return $this->inner->get($key, $default);
    }

    public function set($key, $value, $ttl = null)
    {
        return $this->inner->set($key, $value, $ttl);
    }

    public function delete($key)
    {
        return $this->inner->delete($key);
    }

    public function clear()
    {
        return $this->inner->clear();
    }

    public function getMultiple($keys, $default = null)
    {
        return $this->inner->getMultiple($keys);
    }

    public function setMultiple($values, $ttl = null)
    {
        return $this->inner->setMultiple($values, $ttl);
    }

    public function deleteMultiple($keys)
    {
        return $this->inner->deleteMultiple($keys);
    }

    public function has($key)
    {
        return $this->inner->has($key);
    }
}
