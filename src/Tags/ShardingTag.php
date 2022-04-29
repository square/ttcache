<?php declare(strict_types=1);

namespace Square\TTCache\Tags;

/**
 * Sharding tag will hash the $shardingValue and map it onto a restricted number of $shards.
 * This is also a Heritable tag.
 * It can be used to clear parts of the cache one by one without busting the entire cache.
 * Example:
 *
 * $tt->remember('key', 0, [new ShardingTag('shard:', 'user:123', 20)], fn () => 'cached value');
 *
 * With this applied at the  top level, the entire tree of cached value would have the tag `shard:15` for example.
 * Allowing to clear this tag and invalidate a chunk of the cache without invalidating the entirety of the cache.
 *
 * WHY:
 * A simpler approach would be to apply a global tag like
 *
 * $tt->remember('key', 0, [new HeritableTag('cache-version:2022-02-10')], fn () => 'cached value');
 *
 * This however is risky in cases where your setup isn't enough to support the entirety of the calls you are receiving
 * when the cache is not present. In those cases, invalidating a piece of the cache at a time is a better approach.
 */
class ShardingTag extends HeritableTag implements TagInterface
{
    public function __construct(string $prefix, string $shardingValue, int $numberOfShards)
    {
        parent::__construct(
            $prefix.'-'.(crc32($prefix.$shardingValue) % $numberOfShards)
        );
    }
}
