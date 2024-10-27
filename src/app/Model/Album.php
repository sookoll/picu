<?php

namespace App\Model;

class Album extends Item
{
    private Provider $provider;
    public ?string $cover;
    public ?string $owner;
    public bool $public = false;
    public int $photos = 0;
    public int $videos = 0;

    public function setProvider(Provider $provider): void
    {
        $this->provider = $provider;
    }

    public function getProvider(): Provider
    {
        return $this->provider;
    }
}
