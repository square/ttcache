<?php declare(strict_types=1);

namespace Square\TTCache;

use Square\TTCache\Tags;
use PHPUnit\Framework\TestCase;

class TagsTest extends TestCase
{
    /**
     * @test
     */
    function empty_map_empty_tags()
    {
        $this->assertEmpty(Tags::fromMap([]));
    }

    /**
     * @test
     */
    function transforms_map_to_tag_list()
    {
        $this->assertSame(['users:1'], Tags::fromMap(['users' => 1]));
        $this->assertSame(['users:1', 'products:2', 'locations:3'], Tags::fromMap([
            'users' => '1',
            'products' => '2',
            'locations' => '3',
        ]));
    }
}
