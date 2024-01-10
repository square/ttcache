<?php

declare(strict_types=1);

namespace Square\TTCache\Store;

interface CacheStoreInterface
{
    public function get(string $key, mixed $default = null): mixed;

    public function set(string $key, mixed $value, int|\DateInterval $ttl = null): bool;

    public function delete(string $key): bool;

    public function getMultiple(iterable $keys, mixed $default = null): iterable;

    public function setMultiple(iterable $values, int|\DateInterval $ttl = null): bool;

    public function deleteMultiple(iterable $keys): bool;
}
