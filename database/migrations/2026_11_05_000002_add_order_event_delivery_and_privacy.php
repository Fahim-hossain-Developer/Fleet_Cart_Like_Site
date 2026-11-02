<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_events', function (Blueprint $t) {
            $t->boolean('notify')->default(false);
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->timestamp('sent_at')->nullable();
        });
        Schema::create('customer_privacy_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('status')->default('requested');
            $t->timestamps();
        });
        Schema::table('download_entitlements', function (Blueprint $t) {
            $t->dropForeign(['product_download_id']);
            $t->unsignedBigInteger('product_download_id')->nullable()->change();
            $t->foreign('product_download_id')->references('id')->on('product_downloads')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('download_entitlements', function (Blueprint $t) {
            $t->dropForeign(['product_download_id']);
            $t->foreign('product_download_id')->references('id')->on('product_downloads')->cascadeOnDelete();
        });
        Schema::dropIfExists('customer_privacy_requests');
        Schema::table('order_events', fn (Blueprint $t) => $t->dropColumn(['notify', 'attempts', 'sent_at']));
    }
};
