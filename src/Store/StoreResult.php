<?php declare(strict_types=1);

namespace Square\TTCache\Store;

class StoreResult
{
    protected $error = null;

    protected $value;

    public function __construct($value, $error = null)
    {
        $this->error = $error;
        $this->value = $value;
    }

    public function value()
    {
        return $this->value;
    }

    public function error()
    {
        return $this->error;
    }

    public function hasError()
    {
        return $this->error() !== null;
    }
}
