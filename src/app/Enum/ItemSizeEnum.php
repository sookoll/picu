<?php

namespace App\Enum;

enum ItemSizeEnum: string
{
    use EnumTrait;

    case SQ150 = 'SQ150';
    case SQ320 = 'SQ320';
    case S320 = 'S320';
    case SQ640 = 'SQ640';
    case M640 = 'M640';
    case M800 = 'M800';
    case L1024 = 'L1024';
    case L1600 = 'L1600';
    case L2048 = 'L2048';

    public function label(): string
    {
        return match ($this) {
            self::SQ150 => 'Square 150x150',
            self::SQ320 => 'Square 320x320',
            self::S320 => 'Small 320x?',
            self::SQ640 => 'Square 640x640',
            self::M640 => 'Medium 640x?',
            self::M800 => 'Medium 800x?',
            self::L1024 => 'Large 1024x?',
            self::L1600 => 'Large 1600x?',
            self::L2048 => 'Large 2048x?',
        };
    }

    public function isSquare(): bool
    {
        return ($this === self::SQ150 || $this === self::SQ320 || $this === self::SQ640);
    }
}
