<?php

namespace App\Domain\Customers\Models;

use Illuminate\Database\Eloquent\Model;

/** @property string|null $password_fingerprint */
class AdminLoginChallenge extends Model
{
    protected $attributes = ['attempts' => 0, 'delivery_attempts' => 0, 'remember' => false];

    protected $table = 'admin_login_challenges';

    protected $guarded = ['id'];

    protected $casts = ['delivery_payload' => 'encrypted', 'expires_at' => 'datetime', 'used_at' => 'datetime', 'sent_at' => 'datetime', 'remember' => 'boolean'];

    protected $hidden = ['code_hash', 'delivery_payload'];
}
