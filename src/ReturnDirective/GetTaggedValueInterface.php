<?php declare(strict_types=1);

namespace Square\TTCache\ReturnDirective;

/**
 * Return the TaggedValue and not just the value.
 * This is useful if you need the tags to apply them as surrogate keys
 * for a CDN cache for example.
 */
interface GetTaggedValueInterface
{
    /**
     * @return mixed
     */
    public function value();
}
