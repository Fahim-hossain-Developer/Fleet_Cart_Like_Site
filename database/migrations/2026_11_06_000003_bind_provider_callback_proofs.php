<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gateway_attempts', fn (Blueprint $t) => $t->string('callback_proof_hash', 64)->nullable());
        Schema::table('sms_deliveries', fn (Blueprint $t) => $t->string('mode', 12)->default('sandbox'));
        Schema::table('order_refunds', function (Blueprint $t) {
            $t->unsignedBigInteger('admin_id')->nullable()->change();
            $t->string('gateway', 40)->default('manual');
            $t->string('provider_reference', 191)->nullable();
            $t->unique(['gateway', 'provider_reference']);
        });
    }

    public function down(): void
    {
        Schema::table('order_refunds', function (Blueprint $t) {
            $t->dropUnique(['gateway', 'provider_reference']);
            $t->dropColumn(['gateway', 'provider_reference']);
        });
        Schema::table('sms_deliveries', fn (Blueprint $t) => $t->dropColumn('mode'));
        Schema::table('gateway_attempts', fn (Blueprint $t) => $t->dropColumn('callback_proof_hash'));
    }
};
