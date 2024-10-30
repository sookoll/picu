<?php

namespace App\Model;

use DateTime;

class Photo extends Item
{
    public string $album;
    public ?string $datetaken;
    public int $height;
    public int $width;
    /** @var PhotoSize[] */
    public array $sizes;
}
