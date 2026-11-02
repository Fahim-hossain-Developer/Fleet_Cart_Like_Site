<?php

namespace App\Domain\Marketing\Models;

use Database\Factories\QuestionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property bool $notice_pending
 * @property int $notice_attempts
 */
class Question extends Model
{
    /** @use HasFactory<QuestionFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = ['notice_pending' => 'boolean', 'notice_attempts' => 'integer'];

    protected $attributes = ['notice_pending' => false, 'notice_attempts' => 0, 'status' => 'pending'];

    protected static function newFactory(): QuestionFactory
    {
        return QuestionFactory::new();
    }
}
