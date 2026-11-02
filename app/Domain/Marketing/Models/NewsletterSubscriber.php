<?php

namespace App\Domain\Marketing\Models;

use Database\Factories\NewsletterSubscriberFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NewsletterSubscriber extends Model
{
    /** @use HasFactory<NewsletterSubscriberFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = ['email' => 'encrypted', 'unsubscribe_token' => 'encrypted', 'confirmation_expires_at' => 'datetime', 'consented_at' => 'datetime', 'confirmed_at' => 'datetime', 'unsubscribed_at' => 'datetime', 'generation' => 'integer'];

    protected $attributes = ['status' => 'pending', 'generation' => 0, 'locale' => 'en', 'source' => 'storefront'];

    protected $hidden = ['email', 'email_hash', 'confirmation_hash', 'unsubscribe_hash', 'unsubscribe_token'];

    protected static function newFactory(): NewsletterSubscriberFactory
    {
        return NewsletterSubscriberFactory::new();
    }
}
