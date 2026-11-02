<?php

namespace App\Domain\Content\Models;

use App\Domain\Content\Actions\ValidateContent;
use App\Domain\Content\Events\ContentChanged;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Translatable\HasTranslations;

/**
 * @property string $status
 * @property int $sort
 * @property string|null $slug
 * @property Carbon|null $published_at
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 */
abstract class ContentModel extends Model
{
    use HasTranslations,LogsActivity;

    protected $guarded = ['id'];

    /** @var list<string> */
    public $translatable = ['title', 'body', 'excerpt'];

    protected $attributes = ['status' => 'draft', 'is_active' => true, 'sort' => 0];

    protected $casts = ['is_active' => 'boolean', 'published_at' => 'datetime', 'starts_at' => 'datetime', 'ends_at' => 'datetime', 'sort' => 'integer', 'seo' => 'array', 'config' => 'array', 'tags' => 'array', 'paths' => 'array', 'new_tab' => 'boolean', 'views' => 'integer', 'button_label' => 'array'];

    protected $dispatchesEvents = ['saved' => ContentChanged::class, 'deleted' => ContentChanged::class];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['title', 'slug', 'status', 'is_active', 'sort', 'published_at', 'starts_at', 'ends_at', 'config', 'url', 'parent_id', 'menu_id'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    protected static function booted(): void
    {
        static::saving(fn (self $m) => app(ValidateContent::class)->execute($m));
        static::updating(function (self $m) {
            if (($m instanceof Page || $m instanceof Post) && $m->isDirty('slug')) {
                $prefix = $m instanceof Page ? '/page/' : '/blog/';
                $from = $prefix.$m->getOriginal('slug');
                $to = $prefix.$m->slug;
                DB::table('redirects')->where('from', $to)->delete();
                DB::table('redirects')->where('to', $from)->update(['to' => $to]);
                DB::table('redirects')->updateOrInsert(['from' => $from], ['to' => $to, 'code' => 301, 'created_at' => now(), 'updated_at' => now()]);
            }
        });
    }

    /** @param Builder<static> $query
     * @return Builder<static> */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')->where('is_active', true)->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()))->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }
}
