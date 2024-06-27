<?php

namespace Square\TTCache;

/**
 * Represents values from cache that we fetched but purposefully have not yet verified the tags.
 */
class TentativelyVerifiedTaggedValue extends TaggedValue
{
    /**
     * @var array<string,string|TagInterface> $invalidTags
     */
    public array $invalidTags = [];

    /**
     * @param array<string,string|TagInterface> $invalidTags
     */
    public static function fromTaggedValue(TaggedValue $taggedValue, array $invalidTags): self
    {
        $v = new self($taggedValue->value, $taggedValue->tags);
        $v->invalidTags = $invalidTags;
        return $v;
    }
}
