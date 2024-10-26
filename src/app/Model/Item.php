<?php

namespace App\Model;

use App\Enum\ItemChangeEnum;
use App\Enum\ItemTypeEnum;
use DateTime;

class Item
{
    public string $id;
    public string $fid;
    public ?string $title;
    public ?string $description;
    public ItemTypeEnum $type;
    public string $url;
    public int $sort = 0;
    public DateTime $changed;
    public DateTime $added;
    public ?ItemChangeEnum $itemChange;
}
