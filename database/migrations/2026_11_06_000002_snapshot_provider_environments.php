<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gateway_attempts', fn (Blueprint $t) => $t->string('mode', 12)->default('sandbox'));
        Schema::table('gateway_refunds', function (Blueprint $t) {
            $t->string('mode', 12)->default('sandbox');
            $t->string('provider_state', 32)->nullable();
        });
        Schema::table('courier_bookings', fn (Blueprint $t) => $t->string('mode', 12)->default('sandbox'));
        Schema::table('orders', fn (Blueprint $t) => $t->string('payment_environment', 12)->nullable());
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn('payment_environment'));
        Schema::table('courier_bookings', fn (Blueprint $t) => $t->dropColumn('mode'));
        Schema::table('gateway_refunds', fn (Blueprint $t) => $t->dropColumn(['mode', 'provider_state']));
        Schema::table('gateway_attempts', fn (Blueprint $t) => $t->dropColumn('mode'));
    }
};
