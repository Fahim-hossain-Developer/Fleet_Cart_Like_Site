<?php

namespace App\Domain\Catalog\Models;

use App\Domain\Catalog\Events\CatalogChanged;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Translatable\HasTranslations;

abstract class CatalogModel extends Model
{
    use HasTranslations, LogsActivity;

    protected $dispatchesEvents = ['saved' => CatalogChanged::class, 'deleted' => CatalogChanged::class];

    protected $guarded = ['id'];

    /** @var list<string> */
    public $translatable = ['name'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly($this->getFillable() ?: array_diff($this->getConnection()->getSchemaBuilder()->getColumnListing($this->getTable()), ['id', 'created_at', 'updated_at', 'search_text']))->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
