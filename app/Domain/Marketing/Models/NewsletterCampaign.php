<?php

namespace App\Domain\Marketing\Models;

use App\Domain\Catalog\Models\CatalogModel;
use Database\Factories\NewsletterCampaignFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class NewsletterCampaign extends CatalogModel
{
    /** @use HasFactory<NewsletterCampaignFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = ['send_at' => 'datetime', 'prepared_at' => 'datetime', 'audience_at' => 'datetime'];

    protected $attributes = ['status' => 'draft'];

    protected $hidden = [];

    protected static function newFactory(): NewsletterCampaignFactory
    {
        return NewsletterCampaignFactory::new();
    }

    public $translatable = ['name', 'body'];
}
