<?php declare(strict_types=1);

namespace Square\TTCache\ReturnDirective;

/**
 * This return directive allows to bypass the cache, meaning the value
 * will be returned without being stored in cache.
 * This is useful for example to avoid caching error values
 */
interface BypassCacheInterface
{
    /**
     * @return mixed
     */
    public function value();
}
