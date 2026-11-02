<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_setup_tokens', function (Blueprint $t): void {
            $t->text('delivery_payload')->nullable();
            $t->timestamp('sent_at')->nullable();
            $t->unsignedInteger('attempts')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('account_setup_tokens', fn (Blueprint $t) => $t->dropColumn(['delivery_payload', 'sent_at', 'attempts']));
    }
};
