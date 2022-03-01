<?php declare(strict_types=1);

namespace Square\TTCache\Tags;

/**
 * A tag that can travel to any child node, becoming
 * `global` within a given branch and being applied to every single
 * cached value in that branch.
 */
class HeritableTag implements TagInterface
{
    protected string $tag;

    public function __construct(string $tag)
    {
        $this->tag = $tag;
    }

    public function __toString() : string
    {
        return $this->tag;
    }
}
