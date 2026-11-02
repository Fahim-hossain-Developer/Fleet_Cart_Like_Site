<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracking_consents', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->text('private_data')->nullable();
            $t->timestamp('consented_at');
            $t->timestamp('revoked_at')->nullable();
            $t->timestamp('expires_at')->index();
            $t->timestamps();
        });
        Schema::create('tracking_events', function (Blueprint $t) {
            $t->id();
            $t->uuid('event_id')->unique();
            $t->foreignId('tracking_consent_id')->constrained()->cascadeOnDelete();
            $t->string('name', 24);
            $t->string('source_key', 100)->unique();
            $t->json('data');
            $t->string('source_url', 2048);
            $t->timestamp('occurred_at');
            $t->timestamp('browser_claimed_at')->nullable();
            $t->string('status', 24)->default('pending')->index();
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->string('failure_code', 64)->nullable();
            $t->timestamp('accepted_at')->nullable();
            $t->timestamps();
        });
        Schema::table('orders', function (Blueprint $t) {
            $t->foreignId('tracking_consent_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            $t->dropConstrainedForeignId('tracking_consent_id');
        });
        Schema::dropIfExists('tracking_events');
        Schema::dropIfExists('tracking_consents');
    }
};
