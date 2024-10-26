<?php

namespace App\Model;

class Album extends Item
{
    public Provider $provider;
    public ?string $cover;
    public ?string $owner;
    public bool $public = false;
    public int $photos = 0;
    public int $videos = 0;
}
