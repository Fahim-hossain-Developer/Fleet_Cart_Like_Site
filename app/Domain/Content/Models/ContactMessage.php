<?php

namespace App\Domain\Content\Models;

use Database\Factories\ContactMessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    /** @use HasFactory<ContactMessageFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected $hidden = ['email', 'message'];

    protected $casts = ['email' => 'encrypted', 'message' => 'encrypted', 'is_closed' => 'boolean', 'consented_at' => 'datetime'];

    protected static function newFactory(): ContactMessageFactory
    {
        return ContactMessageFactory::new();
    }
}
