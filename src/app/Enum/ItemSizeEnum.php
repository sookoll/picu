<?php

namespace App\Enum;

enum ItemSizeEnum: string
{
    use EnumTrait;

    case SQ150 = 'sq150';
    case S320 = 's320';
    case M640 = 'm640';
    case M800 = 'm800';
    case L1024 = 'l1024';
    case L1600 = 'l1600';
    case L2048 = 'l2048';

    public function label(): string
    {
        return match ($this) {
            self::SQ150 => 'Square 150x150',
            self::S320 => 'Small 320x?',
            self::M640 => 'Medium 640x?',
            self::M800 => 'Medium 800x?',
            self::L1024 => 'Large 1024x?',
            self::L1600 => 'Large 1600x?',
            self::L2048 => 'Large 2048x?',
        };
    }
}
