<?php

declare(strict_types=1);

namespace Square\TTCache;

use Stringable;

class TaggingKey implements Stringable
{
    public function __construct(public string $key, public array $tags)
    {
    }

    public function __toString(): string
    {
        return $this->key;
    }
}
