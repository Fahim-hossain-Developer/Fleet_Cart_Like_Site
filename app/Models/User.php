<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\LogOptions;

/** @property int $auth_version */
class User extends Authenticatable
{
    protected $attributes = ['auth_version' => 0, 'is_blocked' => false];

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, \Spatie\Activitylog\Traits\LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['is_blocked', 'tags', 'staff_notes'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function setEmailAttribute(?string $email): void
    {
        $this->attributes['email'] = $email ? strtolower(trim($email)) : null;
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name', 'is_blocked', 'tags', 'staff_notes',
        'phone', 'locale', 'currency',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed', 'auth_version' => 'integer', 'is_blocked' => 'boolean', 'tags' => 'array', 'last_login_at' => 'datetime', 'phone_verified_at' => 'datetime',
        ];
    }
}
