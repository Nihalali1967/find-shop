<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['shop_id', 'from_status', 'to_status', 'reason', 'actor_type', 'actor_id'])]
class ShopStatusEvent extends Model
{
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
