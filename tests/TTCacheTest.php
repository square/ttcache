<?php

declare(strict_types=1);

namespace Square\TTCache;

use Memcached;
use PHPUnit\Framework\TestCase;
use Square\TTCache\ReturnDirective\BypassCache;
use Square\TTCache\Tags\HeritableTag;
use Square\TTCache\Tags\ShardingTag;

abstract class TTCacheTest extends TestCase
{
    protected TTCache $tt;

    protected Memcached $mc;

    protected KeyTracker $keyTracker;

    public function setUp(): void
    {
        $this->tt = $this->getTTCache();
    }

    abstract public function getTTCache(): TTCache;

    abstract public function getTTCacheWithKeyTracker(): TTCache;

    /**
     * Returns a TTCache implementation which will fail connecting to the
     * cache store
     */
    abstract public function getBogusTTCache(): TTCache;

    /**
     * @test
     */
    public function cache()
    {
        $v = $this->tt->remember('testkey', fn () => 'hello 1')->value();
        $this->assertSame('hello 1', $v);
        // If we use the same key but a different callback that returns something different, we should still get
        // the previously cached value.
        $v = $this->tt->remember('testkey', fn () => 'hello 2')->value();
        $this->assertSame('hello 1', $v);
    }

    /**
     * @test
     */
    public function clear_by_tags()
    {
        $v = $this->tt->remember('testkey', fn () => 'hello 1', ['tag', 'other:tag'])->value();
        $this->assertSame('hello 1', $v);
        // now it's cached
        $v = $this->tt->remember('testkey', fn () => 'hello 2')->value();
        $this->assertSame('hello 1', $v);
        // clear `tag`
        $this->tt->clearTags('tag');
        $v = $this->tt->remember('testkey', fn () => 'hello 2')->value();
        $this->assertSame('hello 2', $v);
    }

    /**
     * @test
     */
    public function tagged_key()
    {
        $key = new TaggedKey('testkey', ['tag']);
        $v = $this->tt->remember($key, fn () => 'hello 1', ['other:tag'])->value();
        $this->assertSame('hello 1', $v);
        // now it's cached
        $v = $this->tt->remember($key, fn () => 'hello 2', ['other:tag'])->value();
        $this->assertSame('hello 1', $v);
        // clear `tag`
        $this->tt->clearTags('tag');
        $v = $this->tt->remember($key, fn () => 'hello 2', ['other:tag'])->value();
        $this->assertSame('hello 2', $v);
        $v = $this->tt->remember($key, fn () => 'hello 3', ['other:tag'])->value();
        $this->assertSame('hello 2', $v);

        $this->tt->clearTags('other:tag');
        $v = $this->tt->remember($key, fn () => 'hello 3', ['other:tag'])->value();
        $this->assertSame('hello 3', $v);
    }

    /**
     * @test
     */
    public function avoids_root_tags_contamination()
    {
        $v = $this->tt->remember('testkey', fn () => 'hello 1', ['tag', 'other:tag'])->value();
        $this->assertSame('hello 1', $v);
        $v = $this->tt->remember('testkey2', fn () => 'hello 2')->value();
        $this->assertSame('hello 2', $v);
        // now it's cached
        $v = $this->tt->remember('testkey2', fn () => 'hello 3')->value();
        $this->assertSame('hello 2', $v);
        // clear `tag`
        $this->tt->clearTags('tag');
        $v = $this->tt->remember('testkey2', fn () => 'hello 3')->value();
        $this->assertSame('hello 2', $v);
    }

    /**
     * @test
     */
    public function tree_cache()
    {
        $built = $this->tt->remember('main', function () {
            $out = 'hello';
            $out .= $this->tt->remember('sub', tags: ['sub:1'], cb: function () {
                return ' dear ';
            })->value();
            $out .= $this->tt->remember('sub2', tags: ['sub:2'], cb: function () {
                return 'world!';
            })->value();

            return $out;
        })->value();

        $this->assertSame($built, 'hello dear world!');

        // Now it's cached
        $built = $this->tt->remember('main', fn () => 'hello wholesome world!')->value();
        $this->assertSame($built, 'hello dear world!');

        // clear one of the sub tags
        $this->tt->clearTags('sub:1');
        // sub2 is still in cache
        $sub2 = $this->tt->remember('sub2', fn () => 'oh no')->value();
        $this->assertSame('world!', $sub2);

        $built = $this->tt->remember('main', fn () => 'hello wholesome world!')->value();
        $this->assertSame($built, 'hello wholesome world!');
    }

    /**
     * @test
     */
    public function handles_exceptions()
    {
        $built = fn () => $this->tt->remember('main', function () {
            $out = 'hello';
            $out .= $this->tt->remember('sub', tags: ['sub:1'], cb: function () {
                return ' dear ';
            })->value();
            $out .= $this->tt->remember('sub2', tags: ['sub:2'], cb: function () {
                throw new \Exception('whoopsie');
            })->value();

            return $out;
        })->value();

        try {
            $built();
        } catch (\Exception $e) {
            // nothing
        }
        $this->assertSame(' dear ', $this->tt->remember('sub', fn () => 'failure')->value());
        $this->assertSame('failure', $this->tt->remember('main', fn () => 'failure')->value());
        $this->assertSame('failure', $this->tt->remember('sub2', fn () => 'failure')->value());
    }

    /**
     * @test
     */
    public function if_sub_ttl_expires_then_sup_expires_too()
    {
        $built = $this->tt->remember('main', function () {
            $out = 'hello';
            $out .= $this->tt->remember('sub', ttl: 1, tags: ['sub:1'], cb: function () {
                return ' dear ';
            })->value();
            $out .= $this->tt->remember('sub2', tags: ['sub:2'], cb: function () {
                return 'world!';
            })->value();

            return $out;
        })->value();

        $this->assertSame($built, 'hello dear world!');

        // Now it's cached
        $built = $this->tt->remember('main', fn () => 'hello wholesome world!')->value();
        $this->assertSame($built, 'hello dear world!');

        sleep(1);

        // Now it's been evicted due to ttl
        $built = $this->tt->remember('main', fn () => 'hello wholesome world!')->value();
        $this->assertSame($built, 'hello wholesome world!');
    }

    /**
     * @test
     */
    public function cache_can_be_bypassed_based_on_result()
    {
        $counter = $this->counter();
        $built = fn () => $this->tt->remember('main', function () use ($counter) {
            $counter->increment();

            return new BypassCache('hello');
        })->value();

        $this->assertSame($built(), 'hello');
        $this->assertSame(1, $counter->get());
        // The value is not getting cached, subsequent calls still increase the counter
        $this->assertSame($built(), 'hello');
        $this->assertSame(2, $counter->get());
    }

    /**
     * @test
     */
    public function can_retrieve_value_and_its_tags()
    {
        $built = fn () => $this->tt->remember('main', tags: ['abc', 'def'], cb: function () {
            return 'hello';
        });

        $this->assertTrue($built() instanceof Result);
        $this->assertSame($built()->value(), 'hello');
        $this->assertSame(
            $built()->tags(),
            [
                't-'.$this->hash('abc'),
                't-'.$this->hash('def'),
            ],
        );
    }

    /**
     * @test
     */
    public function result_knows_when_it_was_hit_or_miss()
    {
        $built = fn () => $this->tt->remember('main', tags: ['abc', 'def'], cb: function () {
            return 'hello';
        });

        $this->assertTrue($built()->isMiss());
        $this->assertTrue($built()->isHit());

        $this->tt->clearTags('abc');
        $this->assertFalse($built()->isHit());
        $this->assertFalse($built()->isMiss());
    }

    /**
     * @test
     */
    public function deep_tree_cache()
    {
        $counter = $this->counter();
        $built = fn () => $this->tt->remember('main', function () use ($counter) {
            $counter->increment();
            $out = 'hello';
            $out .= $this->tt->remember('sub', tags: ['sub:1'], cb: function () use ($counter) {
                $counter->increment();
                $out = ' dear ';
                $out .= $this->tt->remember('sub2', tags: ['sub:2'], cb: function () use ($counter) {
                    $counter->increment();
                    $out = 'world';
                    $out .= $this->tt->remember('sub3', tags: ['sub:3'], cb: function () use ($counter) {
                        $counter->increment();

                        return '!';
                    })->value();

                    return $out;
                })->value();

                return $out;
            })->value();

            return $out;
        })->value();

        $this->assertSame($built(), 'hello dear world!');
        $this->assertSame($counter->get(), 4, 'expected first call to call all 4 levels.');

        // Now it's cached
        $counter->reset();
        $this->assertSame($built(), 'hello dear world!');
        $this->assertSame($counter->get(), 0, 'expected not clearing any tags to call 0 levels.'); // no callbacks were called

        // clear one of the sub tags
        $counter->reset();
        $this->tt->clearTags('sub:1');
        $this->assertSame($built(), 'hello dear world!');
        $this->assertSame($counter->get(), 2, 'expected clearing tag sub:1 to call only 2 levels.'); // 2 levels of callbacks were called

        // clear deepest sub
        $counter->reset();
        $this->tt->clearTags('sub:3');
        $this->assertSame($built(), 'hello dear world!');
        $this->assertSame($counter->get(), 4, 'expected clearing of deepest tag to call all 4 levels.'); // 4 levels of callbacks were called
    }

    /**
     * @test
     */
    public function caching_from_a_cached_value_still_applies_inner_tags()
    {
        $counter = $this->counter();

        $built = fn () => $this->tt->remember('main', tags: ['uppertag'], cb: function () use ($counter) {
            $counter->increment();
            $out = 'hello';
            $out .= $this->tt->remember('sub', tags: ['sub:1'], cb: function () use ($counter) {
                $counter->increment();

                return ' dear world!';
            })->value();

            return $out;
        });

        $this->assertSame($built()->value(), 'hello dear world!');
        $this->assertSame($built()->tags(), ['t-uppertag', 't-sub:1']);
        $this->assertSame($counter->get(), 2, 'expected first call to call all 4 levels.');

        $this->tt->clearTags(('uppertag'));
        $counter->reset();

        // Only 1 level of re-computing happened. 'sub' was retrieved from cache
        // but its tags still got applied to the upper level
        $this->assertSame($built()->value(), 'hello dear world!');
        $this->assertSame($built()->tags(), ['t-uppertag', 't-sub:1']);
        $this->assertSame($counter->get(), 1, 'expected first call to call all 4 levels.');

        $this->tt->clearTags(('sub:1'));
        $counter->reset();

        // So now when clearing the sub tag, we go 2 levels deep
        $this->assertSame($built()->value(), 'hello dear world!');
        $this->assertSame($built()->tags(), ['t-uppertag', 't-sub:1']);
        $this->assertSame($counter->get(), 2, 'expected first call to call all 4 levels.');
    }

    /**
     * @test
     */
    public function tags_can_get_inherited_from_parent_node()
    {
        $counter = $this->counter();
        $built = fn () => $this->tt->remember('main', tags: ['main', new HeritableTag('global')], cb: function () use ($counter) {
            $counter->increment();
            $out = 'hello';
            $out .= $this->tt->remember('sub', tags: ['sub:1'], cb: function () use ($counter) {
                $counter->increment();
                $out = ' dear ';
                $out .= $this->tt->remember('sub2', tags: ['sub:2', new HeritableTag('subglobal')], cb: function () use ($counter) {
                    $counter->increment();
                    $out = 'world';
                    $out .= $this->tt->remember('sub3', tags: ['sub:3'], cb: function () use ($counter) {
                        $counter->increment();

                        return '!';
                    })->value();

                    return $out;
                })->value();

                return $out;
            })->value();

            return $out;
        })->value();

        $this->assertSame($built(), 'hello dear world!');
        $this->assertSame($counter->get(), 4, 'expected first call to call all 4 levels.');

        // Now it's cached
        $counter->reset();
        $this->assertSame($built(), 'hello dear world!');
        $this->assertSame($counter->get(), 0, 'expected not clearing any tags to call 0 levels.'); // no callbacks were called

        // clear one of the sub tags
        $counter->reset();
        $this->tt->clearTags('sub:1');
        $this->assertSame($built(), 'hello dear world!');
        $this->assertSame($counter->get(), 2, 'expected clearing sub:1 to call 2 levels.'); // 2 levels of callbacks were called

        // clear the heritable tag
        $counter->reset();
        $this->tt->clearTags('global');
        $this->assertSame($built(), 'hello dear world!');
        $this->assertSame($counter->get(), 4, 'expected clearing heritable tag "global" to call 4 levels.'); // 4 levels of callbacks were called

        // clear the heritable tag
        $counter->reset();
        $this->tt->clearTags('subglobal');
        $this->assertSame($built(), 'hello dear world!');
        $this->assertSame($counter->get(), 4, 'expected clarring heritable tag "subglobal" to call 4 levels.'); // 4 levels of callbacks were called
    }

    /**
     * @test
     */
    public function tags_use_wrap_to_add_tags_without_caching()
    {
        $counter = $this->counter();
        $built = fn () => $this->tt->wrap(['main', new HeritableTag('global')], function () use ($counter) {
            $counter->increment();
            $out = 'hello';
            $out .= $this->tt->remember('sub', tags: ['sub:1'], cb: function () use ($counter) {
                $counter->increment();
                $out = ' dear ';
                $out .= $this->tt->wrap(['sub:2', new HeritableTag('subglobal')], cb: function () use ($counter) {
                    $counter->increment();
                    $out = 'world';
                    $out .= $this->tt->remember('sub3', tags: ['sub:3'], cb: function () use ($counter) {
                        $counter->increment();

                        return '!';
                    })->value();

                    return $out;
                });

                return $out;
            })->value();

            return $out;
        });

        $this->assertSame($built(), 'hello dear world!');
        $this->assertSame($counter->get(), 4);

        // Now it's cached but the first call to `wrap` still doesn't cache anything
        $counter->reset();
        $this->assertSame($built(), 'hello dear world!');
        $this->assertSame($counter->get(), 1); // no callbacks were called

        // clear the top level heritable tag should clear everything even though it was added on `wrap` which
        // by itself does not cache anything
        $counter->reset();
        $this->tt->clearTags('global');
        $this->assertSame($built(), 'hello dear world!');
        $this->assertSame($counter->get(), 4); // 2 levels of callbacks were called

        // Same happens with the other heritable tag
        $counter->reset();
        $this->tt->clearTags('global');
        $this->assertSame($built(), 'hello dear world!');
        $this->assertSame($counter->get(), 4); // 2 levels of callbacks were called

        // Clearing `sub:2` added via a nested call to `wrap` also works
        $counter->reset();
        $this->tt->clearTags('sub:2');
        $this->assertSame($built(), 'hello dear world!');
        $this->assertSame($counter->get(), 3); // 2 levels of callbacks were called
    }

    /**
     * @test
     */
    public function additional_tags()
    {
        $counter = $this->counter();
        $built = fn () => $this->tt->remember('main', function () use ($counter) {
            $counter->increment();
            $out = 'hello';
            $out .= $this->tt->remember('sub', tags: ['sub:1', 'subs:all'], cb: function () use ($counter) {
                $counter->increment();
                $out = ' dear ';
                $out .= $this->tt->remember('sub2', tags: ['sub:2'], cb: function () use ($counter) {
                    $counter->increment();
                    $out = 'world';
                    $out .= $this->tt->remember('sub3', tags: ['sub:3', ...Tags::fromMap(['subs' => 'deep', 'ocean' => 'verydeep'])], cb: function () use ($counter) {
                        $counter->increment();

                        return '!';
                    })->value();

                    return $out;
                })->value();

                return $out;
            })->value();

            return $out;
        })->value();

        $this->assertSame($built(), 'hello dear world!');
        $this->assertSame($counter->get(), 4);

        // Now it's cached
        $counter->reset();
        $this->assertSame($built(), 'hello dear world!');
        $this->assertSame($counter->get(), 0); // no callbacks were called

        // clear one of the additional sub tags
        $counter->reset();
        $this->tt->clearTags('subs:all');
        $this->assertSame($built(), 'hello dear world!');
        $this->assertSame($counter->get(), 2); // 2 levels of callbacks were called

        // clear deepest sub
        $counter->reset();
        $this->tt->clearTags('subs:deep');
        $this->assertSame($built(), 'hello dear world!');
        $this->assertSame($counter->get(), 4); // 4 levels of callbacks were called

        // clear deepest sub
        $counter->reset();
        $this->assertSame($built(), 'hello dear world!');
        $this->assertSame($counter->get(), 0); // cached again

        // clear deepest sub
        $counter->reset();
        $this->tt->clearTags('ocean:verydeep');
        $this->assertSame($built(), 'hello dear world!');
        $this->assertSame($counter->get(), 4); // also goes to all levels
    }

    /**
     * @test
     */
    public function sharding_tags_clear_swaths_of_cache()
    {
        $main = $this->counter();
        $sub = $this->counter();
        $built = fn () => $this->tt->remember('main', tags: [new ShardingTag('shard', 'abc', 2)], cb: fn () => 'main'.$main->increment())->value();
        $built2 = fn () => $this->tt->remember('sub', tags: [new ShardingTag('shard', 'def', 2)], cb: fn () => 'sub'.$sub->increment())->value();

        $this->assertSame('main1', $built());
        $this->assertSame('sub1', $built2());
        // Now they're cached
        $this->assertSame('main1', $built());
        $this->assertSame('sub1', $built2());
        // clear a shard tag clears only the value that was on that shard
        $this->tt->clearTags('shard-0');
        $this->assertSame('main2', $built());
        $this->assertSame('sub1', $built2());
        $this->tt->clearTags('shard-1');
        $this->assertSame('main2', $built());
        $this->assertSame('sub2', $built2());
    }

    public function counter()
    {
        return new class()
        {
            protected $c = 0;

            public function increment()
            {
                return ++$this->c;
            }

            public function get()
            {
                return $this->c;
            }

            public function reset()
            {
                $this->c = 0;
            }
        };
    }

    /**
     * @test
     */
    public function retrieving_parts_of_a_collection_still_applies_all_tags()
    {
        $coll = [
            new BlogPost('abc', 'Learn PHP the curved way', '...'),
            new BlogPost('def', 'Learn Python the curved way', '...'),
            new BlogPost('ghi', 'Learn Javascript the curved way', '...'),
            new BlogPost('klm', 'Learn Rust the curved way', '...'),
            new BlogPost('nop', 'Learn Go the curved way', '...'),
        ];

        $this->tt = $this->getTTCacheWithKeyTracker();

        $resultReaderStub = (object) [
            'result' => null,
        ];

        $built = fn () => $this->tt->remember('full-collection', function () use ($coll, $resultReaderStub) {
            $posts = [];
            $keys = [];
            foreach ($coll as $post) {
                $keys[] = __CLASS__.':blog-collection:'.$post->id;
            }
            $resultReaderStub->result = $this->tt->load($keys);

            foreach ($coll as $post) {
                $key = __CLASS__.':blog-collection:'.$post->id;
                $posts[] = $this->tt->remember($key, tags: ['post:'.$post->id], cb: fn () => "<h1>$post->title</h1><hr /><div>$post->content</div>")->value();
            }

            return $posts;
        })->value();

        $this->assertSame([
            '<h1>Learn PHP the curved way</h1><hr /><div>...</div>',
            '<h1>Learn Python the curved way</h1><hr /><div>...</div>',
            '<h1>Learn Javascript the curved way</h1><hr /><div>...</div>',
            '<h1>Learn Rust the curved way</h1><hr /><div>...</div>',
            '<h1>Learn Go the curved way</h1><hr /><div>...</div>',
        ], $built());
        $this->assertSame([
            'k-'.$this->hash('full-collection'),
            'k-'.$this->hash('Square\TTCache\TTCacheTest:blog-collection:abc'),
            'k-'.$this->hash('Square\TTCache\TTCacheTest:blog-collection:def'),
            'k-'.$this->hash('Square\TTCache\TTCacheTest:blog-collection:ghi'),
            'k-'.$this->hash('Square\TTCache\TTCacheTest:blog-collection:klm'),
            'k-'.$this->hash('Square\TTCache\TTCacheTest:blog-collection:nop'),
        ], $this->keyTracker->getRequestedKeys());

        $this->assertInstanceOf(LoadResult::class, $resultReaderStub->result);
        $this->assertEmpty($resultReaderStub->result->loadedKeys());
        $this->assertSame([
            'Square\TTCache\TTCacheTest:blog-collection:abc',
            'Square\TTCache\TTCacheTest:blog-collection:def',
            'Square\TTCache\TTCacheTest:blog-collection:ghi',
            'Square\TTCache\TTCacheTest:blog-collection:klm',
            'Square\TTCache\TTCacheTest:blog-collection:nop',
        ], $resultReaderStub->result->missingKeys());

        // When we call `built()` again, all the data should be pre-loaded and therefore come with a single call to MC
        $resultReaderStub->result = null;
        $this->keyTracker->requestedKeys = [];
        $built();
        $this->assertSame([
            'k-'.$this->hash('full-collection'),
        ], $this->keyTracker->getRequestedKeys());
        $this->assertNull($resultReaderStub->result);

        // Clear tag for "abc" and change the title for "abc"
        $this->tt->clearTags('post:'.$coll[0]->id);
        $resultReaderStub->result = null;
        $this->keyTracker->requestedKeys = [];
        $coll[0]->title = 'Learn PHP the straight way';
        $this->assertSame([
            '<h1>Learn PHP the straight way</h1><hr /><div>...</div>',
            '<h1>Learn Python the curved way</h1><hr /><div>...</div>',
            '<h1>Learn Javascript the curved way</h1><hr /><div>...</div>',
            '<h1>Learn Rust the curved way</h1><hr /><div>...</div>',
            '<h1>Learn Go the curved way</h1><hr /><div>...</div>',
        ], $built());
        $this->assertInstanceOf(LoadResult::class, $resultReaderStub->result);
        $this->assertEquals([
            1 => 'Square\TTCache\TTCacheTest:blog-collection:def',
            2 => 'Square\TTCache\TTCacheTest:blog-collection:ghi',
            3 => 'Square\TTCache\TTCacheTest:blog-collection:klm',
            4 => 'Square\TTCache\TTCacheTest:blog-collection:nop',
        ], $resultReaderStub->result->loadedKeys());
        $this->assertEquals([
            0 => 'Square\TTCache\TTCacheTest:blog-collection:abc',
        ], $resultReaderStub->result->missingKeys());

        // Newly cached value still contains all the tags. So clearing by another tag will also work.
        $this->tt->clearTags('post:'.$coll[1]->id);
        $this->keyTracker->requestedKeys = [];
        $resultReaderStub->result = null;
        $coll[1]->title = 'Learn Python the straight way';
        $this->assertEquals([
            '<h1>Learn PHP the straight way</h1><hr /><div>...</div>',
            '<h1>Learn Python the straight way</h1><hr /><div>...</div>',
            '<h1>Learn Javascript the curved way</h1><hr /><div>...</div>',
            '<h1>Learn Rust the curved way</h1><hr /><div>...</div>',
            '<h1>Learn Go the curved way</h1><hr /><div>...</div>',
        ], $built());
        $this->assertInstanceOf(LoadResult::class, $resultReaderStub->result);
        $this->assertEquals([
            0 => 'Square\TTCache\TTCacheTest:blog-collection:abc',
            2 => 'Square\TTCache\TTCacheTest:blog-collection:ghi',
            3 => 'Square\TTCache\TTCacheTest:blog-collection:klm',
            4 => 'Square\TTCache\TTCacheTest:blog-collection:nop',
        ], $resultReaderStub->result->loadedKeys());
        $this->assertEquals([
            1 => 'Square\TTCache\TTCacheTest:blog-collection:def',
        ], $resultReaderStub->result->missingKeys());
    }

    /**
     * @test
     */
    public function result_still_gets_computed_when_cache_is_down()
    {
        $this->tt = $this->getBogusTTCache();
        $r = $this->tt->remember('hello', fn () => 5);

        $this->assertSame(5, $r->value());
        $this->assertTrue($r->isMiss());
    }

    /**
     * @test
     */
    public function collection_result_still_gets_computed_when_cache_is_down()
    {
        $this->tt = $this->getBogusTTCache();
        $this->tt->wrap([], function () {
            $this->tt->load(['hello1', 'hello2', 'hello3']);

            $r1 = $this->tt->remember('hello1', fn () => 1);
            $r2 = $this->tt->remember('hello2', fn () => 2);
            $r3 = $this->tt->remember('hello3', fn () => 3);

            $this->assertSame(1, $r1->value());
            $this->assertTrue($r1->isMiss());
            $this->assertSame(2, $r2->value());
            $this->assertTrue($r2->isMiss());
            $this->assertSame(3, $r3->value());
            $this->assertTrue($r3->isMiss());
        });
    }

    /**
     * When we ->load() a collection, if some of the keys were not found on the server, we should remember that
     * and not go check the server agin for those keys
     *
     * @group debug
     *
     * @test
     */
    public function collection_non_loaded_members_dont_check_remote_cache_again()
    {
        $this->tt = $this->getTTCacheWithKeyTracker();
        $this->tt->wrap([], function () {
            $this->tt->load(['hello1', 'hello2', 'hello3']);

            $this->tt->remember('hello1', fn () => 1);
            $this->tt->remember('hello2', fn () => 2);
            $this->tt->remember('hello3', fn () => 3);
        });
        $this->assertEquals(3, count($this->keyTracker->requestedKeys));
    }

    /**
     * @test
     */
    public function tag_hashes_match()
    {
        $tags = ['tag', 'other:tag'];
        $r = $this->tt->remember('testkey', fn () => 'hello 1', $tags);
        $this->assertSame($r->tags(), $this->tt->hashTags(...$tags));
    }

    /**
     * The hashing function being used in tests. Using the default here, which is md5 (https://www.php.net/manual/en/function.md5.php)
     */
    protected function hash(string $key): string
    {
        return $key;
    }
}
