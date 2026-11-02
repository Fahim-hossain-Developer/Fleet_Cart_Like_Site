<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_deliveries', fn (Blueprint $t) => $t->text('receiver')->nullable()->change());
        Schema::table('phone_challenges', fn (Blueprint $t) => $t->foreignId('sms_delivery_id')->nullable()->constrained()->restrictOnDelete());
    }

    public function down(): void
    {
        Schema::table('phone_challenges', fn (Blueprint $t) => $t->dropConstrainedForeignId('sms_delivery_id'));
    }
};
