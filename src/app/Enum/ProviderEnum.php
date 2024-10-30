<?php

namespace App\Enum;

enum ProviderEnum: string
{
    use EnumTrait;

    case FLICKR = 'flickr';
    case DISK = 'disk';

    public function label(): string
    {
        return match ($this) {
            self::FLICKR => 'Flickr',
            self::DISK => 'Lokaalsed albumid',
        };
    }
}
