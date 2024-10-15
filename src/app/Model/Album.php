<?php

namespace App\Model;

class Album
{
    public string $id;
    public string $label;
    public string $description;
    public string $cover;
    public int $photos = 0;
    public int $videos = 0;
}
