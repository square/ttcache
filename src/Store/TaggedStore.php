<?php declare(strict_types=1);

namespace Square\TTCache\Store;

use Iterator;
use Psr\SimpleCache\CacheInterface;
use Square\TTCache\ReturnDirective\GetTaggedValueInterface;
use Square\TTCache\TaggedValue;

/**
 * Internal class meant to handle retrieving and storing tagged values for TTCache.
 * TTCache handles the tree and this handles the tags themselves
 */
class TaggedStore
{
    protected const TAGS_TTL = null;

    protected CacheInterface $cache;

    private string $specialKeyDelimeter;

    private const TTL_CACHE_PREFIX = '__TTCache_TTL__';

    public function __construct(CacheInterface $cache, string $specialKeyDelimeter)
    {
        $this->cache = $cache;
        $this->specialKeyDelimeter = $specialKeyDelimeter;
    }

    /**
     * Retrieve a single value from cache and verify its tags
     *
     * @param string $key
     * @return TaggedValue|bool
     */
    public function get(string $key)
    {
        try {
            $r = $this->cache->get($key);
        } catch (CacheStoreException $e) {
            $r = null;
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
                return $r;
            }
        }

        return false;
    }

    /**
     * Stores a value in cache with its current tag hashes
     * returns the value that was stored in cache which can be:
     * - the value that was given (most common)
     * - a TaggedValue wrapping the given value (if using a GetTaggedValueInterface directive)
     *
     * @param string $key
     * @param int|null $ttl
     * @param array $taghashes
     * @param TaggedValue|mixed $value
     * @return mixed
     */
    public function store(string $key, ?int $ttl, array $taghashes, $value)
    {
        // store result
        $v = new TaggedValue($value, $taghashes);

        if ($value instanceof GetTaggedValueInterface) {
            $value = new TaggedValue($value->value(), $taghashes);
            $v = new TaggedValue($value, $taghashes);
        }

        $this->cache->set($key, $v, $ttl);

        return $v->value;
    }

    /**
     * Retrieve multiple values from the cache and return the ones that have valid tags
     *
     * @param array $keys
     * @return array
     */
    public function getMultiple(array $keys)
    {
        $r = $this->cache->getMultiple($keys);
        if ($r instanceof Iterator) {
            $r = iterator_to_array($r);
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

        $allCurrentTagHashes = $this->cache->getMultiple($allTags);
        if ($allCurrentTagHashes instanceof Iterator) {
            $allCurrentTagHashes = iterator_to_array($allCurrentTagHashes);
        }

        $validResults = [];
        /** @var string $k */
        /** @var TaggedValue $tv */
        foreach ($r as $k => $tv) {
            if ($this->tagsAreValid($tv->tags, $allCurrentTagHashes)) {
                $validResults[$k] = $tv;
            }
        }

        return $validResults;
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
                $this->specialKeyDelimeter,
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
