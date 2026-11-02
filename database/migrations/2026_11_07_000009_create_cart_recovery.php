<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_recovery_contacts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('cart_id')->unique()->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->text('captured_name')->nullable();
            $t->text('email')->nullable();
            $t->text('phone')->nullable();
            $t->string('email_hash', 64)->nullable()->index();
            $t->string('phone_hash', 64)->nullable()->index();
            $t->json('channels');
            $t->string('locale', 2)->default('en');
            $t->unsignedInteger('generation')->default(0);
            $t->timestamp('consented_at')->nullable();
            $t->timestamp('last_activity_at')->nullable()->index();
            $t->timestamp('email_verified_at')->nullable();
            $t->timestamp('phone_verified_at')->nullable();
            $t->string('confirmation_hash', 64)->nullable()->unique();
            $t->timestamp('confirmation_expires_at')->nullable();
            $t->string('stop_hash', 64)->nullable()->unique();
            $t->text('stop_token')->nullable();
            $t->string('sms_code_hash')->nullable();
            $t->unsignedTinyInteger('sms_attempts')->default(0);
            $t->timestamp('sms_expires_at')->nullable();
            $t->foreignId('sms_delivery_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamp('stopped_at')->nullable();
            $t->timestamp('converted_at')->nullable();
            $t->timestamps();
        });
        Schema::create('cart_recovery_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('cart_recovery_contact_id')->constrained()->cascadeOnDelete();
            $t->string('kind', 24);
            $t->string('channel', 12);
            $t->unsignedSmallInteger('stage')->default(0);
            $t->unsignedInteger('generation');
            $t->text('payload')->nullable();
            $t->json('snapshot')->nullable();
            $t->string('link_hash', 64)->nullable()->unique();
            $t->string('status', 20)->default('pending')->index();
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->string('failure_code', 64)->nullable();
            $t->timestamp('expires_at');
            $t->timestamp('sent_at')->nullable();
            $t->timestamp('used_at')->nullable();
            $t->foreignId('restored_cart_id')->nullable()->constrained('carts')->nullOnDelete();
            $t->timestamps();
            $t->unique(['cart_recovery_contact_id', 'generation', 'kind', 'channel', 'stage'], 'cart_reminder_unique');
        });
        Schema::create('cart_recovery_restorations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('cart_recovery_contact_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('generation');
            $t->foreignId('destination_cart_id')->constrained('carts')->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['cart_recovery_contact_id', 'generation', 'destination_cart_id'], 'cart_restore_once');
        });
    }

    public function down(): void
    {
        foreach (['cart_recovery_restorations', 'cart_recovery_messages', 'cart_recovery_contacts'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
