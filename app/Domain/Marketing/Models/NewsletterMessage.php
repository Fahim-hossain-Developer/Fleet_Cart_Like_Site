<?php

namespace App\Domain\Marketing\Models;

use Database\Factories\NewsletterMessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NewsletterMessage extends Model
{
    /** @use HasFactory<NewsletterMessageFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = ['payload' => 'encrypted:array', 'generation' => 'integer', 'attempts' => 'integer', 'expires_at' => 'datetime', 'sent_at' => 'datetime'];

    protected $attributes = ['status' => 'pending', 'attempts' => 0];

    protected $hidden = ['payload'];

    protected static function newFactory(): NewsletterMessageFactory
    {
        return NewsletterMessageFactory::new();
    }
}
