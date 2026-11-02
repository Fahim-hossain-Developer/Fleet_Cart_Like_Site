<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['notice_deliveries', 'cart_recovery_messages'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->foreignId('sms_delivery_id')->nullable()->constrained()->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['cart_recovery_messages', 'notice_deliveries'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropConstrainedForeignId('sms_delivery_id');
            });
        }
    }
};
