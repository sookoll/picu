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
    private ?ItemChangeEnum $itemChange = null;

    public function getItemChange(): ?ItemChangeEnum
    {
        return $this->itemChange;
    }

    public function setItemChange(?ItemChangeEnum $itemChange): void
    {
        $this->itemChange = $itemChange;
    }
}
