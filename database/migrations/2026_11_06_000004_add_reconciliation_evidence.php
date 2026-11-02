<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gateway_refunds', function (Blueprint $t) {
            $t->unsignedBigInteger('verified_amount')->nullable();
            $t->string('verified_currency', 3)->nullable();
            $t->string('verified_reference', 191)->nullable();
        });
        Schema::table('integration_events', function (Blueprint $t) {
            $t->string('mode', 12)->default('sandbox');
            $t->unsignedTinyInteger('attempts')->default(0);
        });
        Schema::table('admin_login_challenges', fn (Blueprint $t) => $t->string('password_fingerprint', 64)->nullable());
        Schema::create('courier_collections', function (Blueprint $t) {
            $t->id();
            $t->foreignId('courier_booking_id')->constrained()->restrictOnDelete();
            $t->foreignId('admin_id')->constrained('admins');
            $t->uuid('idempotency_key')->unique();
            $t->unsignedBigInteger('amount');
            $t->string('reference', 150);
            $t->timestamps();
            $t->unique(['courier_booking_id', 'reference']);
        });
        Schema::table('courier_remittances', fn (Blueprint $t) => $t->unique(['courier_booking_id', 'reference']));
    }

    public function down(): void
    {
        // InnoDB may replace the original FK index with this composite unique index.
        Schema::table('courier_remittances', fn (Blueprint $t) => $t->index('courier_booking_id', 'courier_remittances_booking_lookup'));
        Schema::table('courier_remittances', fn (Blueprint $t) => $t->dropUnique(['courier_booking_id', 'reference']));
        Schema::dropIfExists('courier_collections');
        Schema::table('admin_login_challenges', fn (Blueprint $t) => $t->dropColumn('password_fingerprint'));
        Schema::table('integration_events', fn (Blueprint $t) => $t->dropColumn(['mode', 'attempts']));
        Schema::table('gateway_refunds', fn (Blueprint $t) => $t->dropColumn(['verified_amount', 'verified_currency', 'verified_reference']));
    }
};
