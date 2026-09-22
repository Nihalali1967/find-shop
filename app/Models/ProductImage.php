<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_id', 'path', 'thumb_path', 'sort_order'])]
class ProductImage extends Model
{
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Files live on the `public` disk, so they are served from /storage, not /.
     */
    public function url(): string
    {
        return $this->toUrl($this->path);
    }

    public function thumbUrl(): string
    {
        return $this->toUrl($this->thumb_path ?: $this->path);
    }

    protected function toUrl(?string $path): string
    {
        if (! $path) {
            return '';
        }

        return str_starts_with($path, 'http') ? $path : asset('storage/'.ltrim($path, '/'));
    }
}
