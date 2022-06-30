<?php declare(strict_types=1);

namespace Square\TTCache\Store;

use Iterator;
use Psr\Cache\CacheException;
use Psr\SimpleCache\CacheException as SimpleCacheCacheException;
use Psr\SimpleCache\CacheInterface;
use Square\TTCache\TaggedValue;

/**
 * Internal class meant to handle retrieving and storing tagged values for TTCache.
 * TTCache handles the tree and this handles the tags themselves
 */
class TaggedStore
{
    protected const TAGS_TTL = null;

    protected CacheInterface $cache;

    private const TTL_CACHE_PREFIX = '__TTCache_TTL__';

    public function __construct(CacheInterface $cache)
    {
        $this->cache = $cache;
    }

    /**
     * Retrieve a single value from cache and verify its tags
     *
     * @param string $key
     * @return StoreResult
     */
    public function get(string $key) : StoreResult
    {
        try {
            $r = $this->cache->get($key);
        } catch (CacheException | SimpleCacheCacheException $e) {
            return new StoreResult(null, $e);
        }

        if ($r) {
            /** @var TaggedValue $r */
            $storedtags = array_keys($r->tags);
            $currentHashes = [];
            if (!empty($storedtags)) {
                $currentHashes = $this->cache->getMultiple($storedtags);
                if ($currentHashes instanceof Iterator) {
                    $currentHashes = iterator_to_array($currentHashes);
                }
            }
            if ($this->tagsAreValid($r->tags, $currentHashes)) {
                return new StoreResult($r);
            }
        }

        return new StoreResult(null);
    }

    /**
     * Stores a value in cache with its current tag hashes
     * returns the value that was stored in cache
     *
     * @param string $key
     * @param int|null $ttl
     * @param array $taghashes
     * @param TaggedValue|mixed $value
     * @return StoreResult
     */
    public function store(string $key, ?int $ttl, array $taghashes, $value) : StoreResult
    {
        // store result
        $v = new TaggedValue($value, $taghashes);

        try {
            $this->cache->set($key, $v, $ttl);
        } catch (CacheException | SimpleCacheCacheException $e) {
            return new StoreResult($v->value, $e);
        }

        return new StoreResult($v->value);
    }

    /**
     * Retrieve multiple values from the cache and return the ones that have valid tags
     *
     * @param array $keys
     * @return StoreResult
     */
    public function getMultiple(array $keys) : StoreResult
    {
        try {
            $r = $this->cache->getMultiple($keys);
        } catch (CacheException | SimpleCacheCacheException $e) {
            return new StoreResult([], $e);
        }


        if ($r instanceof Iterator) {
            try {
                $r = iterator_to_array($r);
            } catch (CacheException | SimpleCacheCacheException $e) {
                return new StoreResult([], $e);
            }
        }

        $allTags = [];
        /** @var TaggedValue $tv */
        foreach ($r as $k => $tv) {
            if (!$tv instanceof TaggedValue) {
                unset($r[$k]);
                continue;
            }
            $allTags = array_merge($allTags, array_keys($tv->tags));
        }

        $allCurrentTagHashes = [];
        if (!empty($allTags)) {
            $allCurrentTagHashes = $this->cache->getMultiple($allTags);
            if ($allCurrentTagHashes instanceof Iterator) {
                $allCurrentTagHashes = iterator_to_array($allCurrentTagHashes);
            }
        }

        $validResults = [];
        /** @var string $k */
        /** @var TaggedValue $tv */
        foreach ($r as $k => $tv) {
            if ($this->tagsAreValid($tv->tags, $allCurrentTagHashes)) {
                $validResults[$k] = $tv;
            }
        }

        return new StoreResult($validResults);
    }

    /**
     * Get the current taghashes from the cache store or create and store new ones if they don't exist
     */
    public function fetchOrMakeTagHashes(array $tags, ?int $ttl = null) : array
    {
        // Should the cache be marked as readonly mode?
        $roCache = false;
        $tagHashes = [];
        $ttlTag = '';
        if ($ttl !== null) {
            $ttlTag = implode(
                '-',
                [
                    self::TTL_CACHE_PREFIX,
                    'ttl',
                    $ttl,
                    $this->generateHash(),
                ],
            );
            $tags = [$ttlTag, ... $tags];
        }

        if (!empty($tags)) {
            try {
                $tagHashes = $this->cache->getMultiple($tags);
                if ($tagHashes instanceof Iterator) {
                    $tagHashes = iterator_to_array($tagHashes) ;
                }
                $tagHashes = array_filter(
                    $tagHashes,
                    static fn($v) => $v !== null,
                );
            } catch (CacheStoreException $e) {
                $roCache = true;
            }
        }

        // Find missing tag hashes
        $missingHashes = [];
        foreach ($tags as $tag) {
            if (!array_key_exists($tag, $tagHashes)) {
                $missingHashes[$tag] = $this->generateHash();
            }
        }
        if (!$roCache) {
            // Add missing hashes to MC
            $this->cache->setMultiple($missingHashes, self::TAGS_TTL);
            if ($ttl !== null) {
                $this->cache->set($ttlTag, $missingHashes[$ttlTag], $ttl);
            } else {
                unset($missingHashes[$ttlTag]);
            }
        }

        return ['taghashes' => array_merge($tagHashes, $missingHashes), 'readonly' => $roCache];
    }

    /**
     * Makes any value associated with any of the given tags invalid in the cache
     *
     * @param string ...$tags
     * @return void
     */
    public function clearTags(string ...$tags) : void
    {
        $tagHashes = [];
        foreach ($tags as $tag) {
            $tagHashes[$tag] = $this->generateHash();
        }
        $this->cache->setMultiple($tagHashes, self::TAGS_TTL);
    }

    /**
     * Creates a random value to be used as a tag's hash
     *
     * @return string
     */
    protected function generateHash() : string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Verify that the tagHashes are valid when compared to the cache store's current hashes
     *
     * @param array $tagHashes the tag hashes retrieved on a cached value
     * @param array $currentHashes the tag hashes as they now exist in the cache
     * @return boolean
     */
    protected function tagsAreValid(array $tagHashes, array $currentHashes): bool
    {
        foreach ($tagHashes as $tag => $hash) {
            if ($hash !== ($currentHashes[$tag] ?? null)) {
                return false;
            }
        }

        return true;
    }
}
