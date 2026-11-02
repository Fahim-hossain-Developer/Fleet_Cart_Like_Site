<?php

namespace App\Domain\Payments\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $mode
 * @property string|null $callback_proof_hash
 * @property int $amount
 * @property int|null $verified_amount
 */
class GatewayAttempt extends Model
{
    protected $attributes = ['mode' => 'sandbox', 'status' => 'created'];

    protected $table = 'gateway_attempts';

    protected $guarded = ['id'];

    protected $casts = ['amount' => 'integer', 'verified_amount' => 'integer', 'verified_at' => 'datetime', 'applied_at' => 'datetime', 'lease_until' => 'datetime', 'execution_started_at' => 'datetime'];

    protected $hidden = ['redirect_url', 'callback_proof_hash'];
}
