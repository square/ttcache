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

    protected $error;

    /**
     * @param array $loadedKeys
     * @param array $missingKeys
     */
    public function __construct(array $loadedKeys, array $missingKeys, $error = null)
    {
        $this->loadedKeys = $loadedKeys;
        $this->missingKeys = $missingKeys;
        $this->error = $error;
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

    public function hasError() : bool
    {
        return $this->error !== null;
    }

    public function error()
    {
        return $this->error;
    }
}
