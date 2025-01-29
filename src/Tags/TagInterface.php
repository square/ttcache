<?php declare(strict_types=1);

namespace Square\TTCache\Tags;

use Stringable;

interface TagInterface extends Stringable
{
    public function __toString() : string;
}
