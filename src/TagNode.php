<?php declare(strict_types=1);

namespace Square\TTCache;

/**
 * Creates a tree structure of tag hashes
 */
class TagNode
{
    protected array $children = [];

    /**
     * List of taghashes on the current node ['tag_name' => 'random_hash_value']
     */
    protected array $tags = [];

    /**
     * A list of tag (the names without the random hashes) that must be passed on to the children
     */
    protected array $heritableTags = [];

    /**
     * Preloaded values that can be retrieved from this node
     * or any descendant from memory instead of going to the cache store
     */
    protected array $localcache = [];

    protected ?TagNode $parent;

    /**
     * Create a child to the current node with the given taghashes and returns the child
     */
    public function child(array $tagHashes) : TagNode
    {
        $c = new TagNode();
        $this->children[] = $c;
        $c->tags = $tagHashes;
        $c->parent = $this;
        $c->heritableTags = $this->heritableTags;

        // Heritable tags travel to the child
        foreach ($this->heritableTags as $tagName) {
            $c->tags[$tagName] = $this->tags[$tagName];
        }

        return $c;
    }

    /**
     * Recursively gets the taghashes from this node and all of its descendants
     */
    public function tagHashes() : array
    {
        $tagHashes = $this->tags;
        foreach ($this->children as $child) {
            $tagHashes = array_merge($tagHashes, $child->tagHashes());
        }

        return array_unique($tagHashes);
    }

    /**
     * Appends new tags to the list of tags that get inherited through out the tag tree
     */
    public function addHeritableTags(array $tagNames)
    {
        $this->heritableTags = array_merge($this->heritableTags, $tagNames);
    }

    /**
     * Adds a set of key / value to the existing local cache.
     * This allows multiple calls to `$tt->load` within the same scope
     */
    public function addToCache(array $kv)
    {
        $this->localcache = array_merge($this->localcache, $kv);
    }

    /**
     * Whether or not a given key matches a record in this node's
     * local cache or any of its parents.
     */
    public function inCache(string $key) : bool
    {
        if (array_key_exists($key, $this->localcache)) {
            return true;
        }
        if (isset($this->parent)) {
            return $this->parent->inCache($key);
        }
        return false;
    }

    /**
     * Retrieve a value from this node's local cache or one of its
     * parent's local cache
     */
    public function getFromCache(string $key) : ?TaggedValue
    {
        if (array_key_exists($key, $this->localcache)) {
            return $this->localcache[$key];
        }
        if (isset($this->parent)) {
            return $this->parent->getFromCache($key);
        }
        return null;
    }
}
