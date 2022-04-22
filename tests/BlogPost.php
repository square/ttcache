<?php

namespace Square\TTCache;

class BlogPost
{
    public string $id;

    public string $title;

    public string $content;

    public function __construct(string $id, string $title, string $content)
    {
        $this->id = $id;
        $this->title = $title;
        $this->content = $content;
    }
}
