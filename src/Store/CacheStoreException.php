<?php declare(strict_types=1);

namespace Square\TTCache\Store;

use Psr\SimpleCache\CacheException;
use Exception;

class CacheStoreException extends Exception implements CacheException
{
}
