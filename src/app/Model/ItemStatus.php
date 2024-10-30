<?php

namespace App\Model;

use App\Enum\ItemStatusEnum;

class ItemStatus
{
    public ItemStatusEnum $type;
    public array $data;

    public function __construct(ItemStatusEnum $type, array $data = [])
    {
        $this->type = $type;
        $this->data = $data;
    }
}
