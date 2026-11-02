<?php

namespace App\Domain\Customers\Models;

use Database\Factories\SocialIdentityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SocialIdentity extends Model
{
    /** @use HasFactory<SocialIdentityFactory> */
    use HasFactory;

    protected static function newFactory(): SocialIdentityFactory
    {
        return SocialIdentityFactory::new();
    }

    protected $guarded = ['id'];

    protected $casts = ['subject' => 'encrypted'];

    protected $hidden = ['subject', 'subject_hash'];
}
