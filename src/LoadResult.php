<?php

namespace Square\TTCache;

/**
 * Provides information about a cache load that was performed.
 */
class LoadResult
{
    /**
     * @var array<string|int,string>
     */
    protected array $loadedKeys;

    /**
     * @var array<string|int,string>
     */
    protected array $missingKeys;

    /**
     * @param array $loadedKeys
     * @param array $missingKeys
     */
    public function __construct(array $loadedKeys, array $missingKeys)
    {
        $this->loadedKeys = $loadedKeys;
        $this->missingKeys = $missingKeys;
    }

    /**
     * @return array
     */
    public function loadedKeys(): array
    {
        return $this->loadedKeys;
    }

    /**
     * @return array
     */
    public function missingKeys(): array
    {
        return $this->missingKeys;
    }
}
