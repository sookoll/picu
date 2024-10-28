<?php

namespace App\Model;

class Album extends Item
{
    public ?string $cover;
    public ?string $owner;
    public bool $public = false;
    public int $photos = 0;
    public int $videos = 0;
    private Provider $provider;
    private Photo $coverItem;

    public function getProvider(): Provider
    {
        return $this->provider;
    }

    public function setProvider(Provider $provider): void
    {
        $this->provider = $provider;
    }

    public function getCoverItem(): Photo
    {
        return $this->coverItem;
    }

    public function setCoverItem(Photo $coverItem): void
    {
        $this->coverItem = $coverItem;
    }
}
