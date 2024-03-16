<?php

declare(strict_types=1);

namespace Square\TTCache;

/**
 * Marker when using ->load() to remember that this was already checked and there is no need to check again
 */
class KnownMiss extends TaggedValue
{
    public function __construct()
    {
        parent::__construct(null, []);
    }

    public function hasError(): bool
    {
        return false;
    }

    public function error(): ?\Throwable
    {
        return null;
    }
}
