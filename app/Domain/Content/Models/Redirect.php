<?php

namespace App\Domain\Content\Models;

use App\Domain\Content\Actions\SafeContent;
use Database\Factories\RedirectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class Redirect extends Model
{
    /** @use HasFactory<RedirectFactory> */
    use HasFactory;

    protected static function newFactory(): RedirectFactory
    {
        return RedirectFactory::new();
    }

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::saving(function (self $m) {
            validator($m->only(['from', 'to', 'code']), ['from' => 'required|string|max:255|regex:~^/[a-zA-Z0-9/_-]*$~D', 'to' => 'required|string|max:2048', 'code' => 'required|integer|in:301,302'])->validate();
            if (! SafeContent::link($m->to) || preg_match('~^/(?:admin|account|checkout|payments|integrations|cart-recovery|newsletter|login|register|email-code|phone-code)(?:/|$)~', $m->from)) {
                throw ValidationException::withMessages(['from' => __('content.invalid_content')]);
            }
            $seen = [$m->from];
            $to = $m->to;
            for ($i = 0; $i < 6; $i++) {
                if (in_array($to, $seen, true)) {
                    throw ValidationException::withMessages(['to' => __('content.invalid_content')]);
                }
                $seen[] = $to;
                $next = self::where('from', $to)->when($m->exists, fn ($q) => $q->whereKeyNot($m->id))->first();
                if (! $next) {
                    return;
                }$to = $next->to;
            }
            throw ValidationException::withMessages(['to' => __('content.invalid_content')]);
        });
    }
}
