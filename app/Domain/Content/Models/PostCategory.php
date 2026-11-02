<?php

namespace App\Domain\Content\Models;

use Database\Factories\PostCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PostCategory extends ContentModel
{
    /** @use HasFactory<PostCategoryFactory> */
    use HasFactory;

    protected $table = 'post_categories';

    protected static function newFactory(): PostCategoryFactory
    {
        return PostCategoryFactory::new();
    }

    /** @return HasMany<Post,$this> */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}
