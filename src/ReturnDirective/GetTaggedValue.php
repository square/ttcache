<?php declare(strict_types=1);

namespace Square\TTCache\ReturnDirective;

class GetTaggedValue implements GetTaggedValueInterface
{
    /**
     * @var mixed
     */
    public $value;

    /**
     * @param mixed $value
     */
    public function __construct($value)
    {
        $this->value = $value;
    }

    /**
     * @return mixed
     */
    public function value()
    {
        return $this->value;
    }
}

