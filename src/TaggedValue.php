<?php declare(strict_types=1);

namespace Square\TTCache;

/**
 * Represents a cached value along its taghashes in the cache store.
 * When retrieved from cache, the taghashes need to be verified against the current hashes in cache
 * if valid, the `value` can be used.
 */
class TaggedValue
{
    /**
     * The value that we are trying to store in cache
     * @var mixed
     */
    public $value;

    /**
     * An array of tag hashes ['tag_name' => 'random_hash_value']
     * if the random hash value for the given tag_name is still the same in the
     * cache for all given tags, then the tagged value is valid and usable
     */
    public array $tags;

    public function __construct($value, array $tags)
    {
        $this->value = $value;
        $this->tags = $tags;
    }
}
