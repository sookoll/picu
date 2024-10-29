<?php

namespace App\Model;

use App\Enum\ItemTypeEnum;

class Item
{
    public string $id;
    public string $fid;
    public ?string $title;
    public ?string $description;
    public ItemTypeEnum $type;
    public string $url;
    public int $sort = 0;
    private ?ItemStatus $status = null;

    public function getStatus(): ?ItemStatus
    {
        return $this->status;
    }

    public function setStatus(?ItemStatus $status): void
    {
        $this->status = $status;
    }
}
