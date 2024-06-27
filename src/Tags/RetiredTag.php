<?php

declare(strict_types=1);

namespace Square\TTCache\Tags;

class RetiredTag implements TagInterface
{
    public function __construct(private string|TagInterface $value)
    { }
    
    public function __toString() : string
    {
        if ($this->value instanceof TagInterface) {
            return (string) $this->value;
        }
        return $this->value;
    }

    public function isHeritable(): bool
    {
        return $this->value instanceof HeritableTag;
    }
}

