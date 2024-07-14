<?php

namespace Square\TTCache\Store;

interface CanResetExpiryOnRead
{
    /** 
     * Get the value for the given key and reset its expiry to the given TTL.
     * @param string $key
     * @param int $ttl
     * @return mixed
     */
    public function getAndResetExpiry(string $key, int $ttl): mixed;

    /** 
     * Get the value for the given key and reset its expiry to the given TTL.
     * @param iterable $keys
     * @param int $ttl
     * @return iterable
     */
    public function getMultipleAndResetExpiry(iterable $keys, int $ttl):  iterable;
}
