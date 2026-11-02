<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('type');
            $t->morphs('notifiable');
            $t->text('data');
            $t->timestamp('read_at')->nullable();
            $t->string('dedupe_key', 64)->unique();
            $t->timestamps();
        });
        Schema::create('notification_preferences', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('topic', 20);
            $t->string('channel', 12);
            $t->boolean('is_enabled')->default(false);
            $t->timestamp('consented_at')->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'topic', 'channel'], 'notice_preference_unique');
        });
        Schema::create('notice_deliveries', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('notification_id')->constrained('notifications')->cascadeOnDelete();
            $t->string('channel', 12);
            $t->string('status', 20)->default('pending')->index();
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->string('failure_code', 64)->nullable();
            $t->timestamp('sent_at')->nullable();
            $t->timestamps();
            $t->unique(['notification_id', 'channel']);
        });
        Schema::create('product_watches', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->string('kind', 12);
            $t->boolean('is_active')->default(true);
            $t->boolean('last_available')->default(false);
            $t->unsignedBigInteger('last_price')->default(0);
            $t->unsignedBigInteger('sequence')->default(0);
            $t->timestamp('notified_at')->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'product_id', 'kind']);
        });
    }

    public function down(): void
    {
        foreach (['product_watches', 'notice_deliveries', 'notification_preferences', 'notifications'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
