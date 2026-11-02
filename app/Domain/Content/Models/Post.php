<?php

namespace App\Domain\Content\Models;

use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Post extends ContentModel
{
    /** @use HasFactory<PostFactory> */
    use HasFactory;

    protected $table = 'posts';

    protected static function newFactory(): PostFactory
    {
        return PostFactory::new();
    }

    /** @return BelongsTo<PostCategory,$this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(PostCategory::class, 'post_category_id');
    }
}
