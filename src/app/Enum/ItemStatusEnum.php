<?php

namespace App\Enum;

enum ItemStatusEnum: string
{
    use EnumTrait;

    case NEW = 'new';
    case CHANGE = 'change';
    case DELETE = 'delete';
    case OK = 'ok';
}
