<?php declare(strict_types=1);

namespace Square\TTCache\Store;

use Throwable;

class StoreResult
{
    protected ?Throwable $error = null;

    protected $value;

    public function __construct($value, ?Throwable $error = null)
    {
        $this->error = $error;
        $this->value = $value;
    }

    public function value()
    {
        return $this->value;
    }

    public function error() : ?Throwable
    {
        return $this->error;
    }

    public function hasError() : bool
    {
        return $this->error() !== null;
    }
}
