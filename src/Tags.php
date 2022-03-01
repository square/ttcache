<?php declare(strict_types=1);

namespace Square\TTCache;

/**
 * Utility class to work with tags
 */
class Tags
{
    /**
     * Sometimes it can be convenient to create tags as a map. For example if you want to tag values with a user id
     * and a product id, you want to create the tags: ['user:1', 'product:2'].
     * This method allows you to create those via Tags::fromMap(['user' => $user_id, 'product' => $product_id]);
     */
    public static function fromMap(array $tagMap) : array
    {
        $tags = [];
        foreach ($tagMap as $k => $v) {
            $tags[] = "$k:$v";
        }

        return $tags;
    }
}
