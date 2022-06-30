<?php

namespace Square\TTCache;

use Throwable;

class Result
{
    protected bool $hit;

    /**
     * @var mixed
     */
    protected $value;

    protected array $tags;

    protected ?Throwable $error = null;

    /**
     * @param mixed $value
     * @param boolean $hit
     * @param array $tags
     */
    public function __construct($value, bool $hit, $tags = [])
    {
        $this->value = $value;
        $this->hit = $hit;
        $this->tags = $tags;
    }

    public function isHit() : bool
    {
        return $this->hit;
    }

    public function isMiss() : bool
    {
        return !$this->hit;
    }

    public function tags() : array
    {
        return $this->tags;
    }

    /**
     * @return mixed
     */
    public function value()
    {
        return $this->value;
    }

    public function hasError() : bool
    {
        return $this->error !== null;
    }

    public function error() : ?Throwable
    {
        return $this->error;
    }

    public function withError(?Throwable $error)
    {
        $this->error = $error;
        return $this;
    }

    public static function fromTaggedValue(TaggedValue $tv, bool $hit)
    {
        return new self(
            $tv->value,
            $hit,
            array_keys($tv->tags)
        );
    }
}
