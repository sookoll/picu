<?php

namespace App\Enum;

enum ItemTypeEnum: string
{
    use EnumTrait;

    case IMAGE = 'image';
    case VIDEO = 'video';
}
