<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_connections', function (Blueprint $t) {
            $t->id();
            $t->string('driver', 40)->unique();
            $t->string('kind', 20);
            $t->boolean('is_enabled')->default(false);
            $t->string('mode', 12)->default('sandbox');
            $t->json('config')->nullable();
            $t->timestamps();
        });
        Schema::table('orders', function (Blueprint $t) {
            $t->unsignedBigInteger('advance_required')->default(0);
        });
        Schema::table('order_payments', function (Blueprint $t) {
            $t->unsignedBigInteger('admin_id')->nullable()->change();
            $t->string('gateway', 40)->default('manual');
            $t->string('provider_reference', 191)->nullable();
            $t->unique(['gateway', 'provider_reference']);
        });
        Schema::create('gateway_attempts', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->uuid('idempotency_key')->unique();
            $t->foreignId('order_id')->constrained()->restrictOnDelete();
            $t->string('driver', 40);
            $t->string('merchant_reference', 32)->unique();
            $t->string('provider_reference', 191)->nullable()->index();
            $t->string('currency', 3);
            $t->unsignedBigInteger('amount');
            $t->string('status', 32)->default('created')->index();
            $t->string('provider_state', 32)->nullable();
            $t->text('redirect_url')->nullable();
            $t->string('failure_code', 64)->nullable();
            $t->unsignedBigInteger('verified_amount')->nullable();
            $t->string('verified_currency', 3)->nullable();
            $t->string('verified_transaction', 191)->nullable();
            $t->timestamp('verified_at')->nullable();
            $t->timestamp('applied_at')->nullable();
            $t->timestamp('lease_until')->nullable();
            $t->timestamp('execution_started_at')->nullable();
            $t->foreignId('order_payment_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('gateway_refunds', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->uuid('idempotency_key')->unique();
            $t->foreignId('order_id')->constrained()->restrictOnDelete();
            $t->foreignId('gateway_attempt_id')->constrained()->restrictOnDelete();
            $t->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $t->string('merchant_reference', 32)->unique();
            $t->string('provider_reference', 191)->nullable();
            $t->string('currency', 3);
            $t->unsignedBigInteger('amount');
            $t->string('status', 32)->default('created')->index();
            $t->string('failure_code', 64)->nullable();
            $t->text('reason');
            $t->json('restock_items');
            $t->timestamp('lease_until')->nullable();
            $t->timestamp('verified_at')->nullable();
            $t->foreignId('order_refund_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('courier_bookings', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->uuid('idempotency_key')->unique();
            $t->foreignId('order_id')->unique()->constrained()->restrictOnDelete();
            $t->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $t->string('driver', 40);
            $t->string('merchant_reference', 32)->unique();
            $t->string('provider_reference', 191)->nullable();
            $t->string('tracking_no', 191)->nullable();
            $t->string('status', 32)->default('created')->index();
            $t->string('provider_status', 50)->nullable();
            $t->json('items');
            $t->unsignedBigInteger('cod_expected');
            $t->unsignedBigInteger('reported_collected')->nullable();
            $t->unsignedBigInteger('remitted_amount')->default(0);
            $t->unsignedBigInteger('delivery_fee')->nullable();
            $t->string('failure_code', 64)->nullable();
            $t->timestamp('lease_until')->nullable();
            $t->timestamp('last_checked_at')->nullable();
            $t->foreignId('shipment_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('courier_remittances', function (Blueprint $t) {
            $t->id();
            $t->foreignId('courier_booking_id')->constrained()->restrictOnDelete();
            $t->foreignId('admin_id')->constrained('admins');
            $t->uuid('idempotency_key')->unique();
            $t->unsignedBigInteger('amount');
            $t->string('reference', 150);
            $t->timestamps();
        });
        Schema::create('integration_events', function (Blueprint $t) {
            $t->id();
            $t->string('dedupe_key', 64)->unique();
            $t->string('kind', 20);
            $t->string('driver', 40);
            $t->string('external_id', 191);
            $t->string('event_type', 100);
            $t->string('payload_hash', 64);
            $t->string('reference', 191)->nullable();
            $t->string('status', 32)->default('received');
            $t->string('failure_code', 64)->nullable();
            $t->timestamp('processed_at')->nullable();
            $t->timestamps();
        });
        Schema::create('sms_deliveries', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->text('receiver');
            $t->text('payload')->nullable();
            $t->string('driver', 40);
            $t->string('status', 32)->default('pending')->index();
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->string('provider_reference', 191)->nullable();
            $t->string('failure_code', 64)->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamp('sent_at')->nullable();
            $t->timestamps();
        });
        Schema::create('phone_challenges', function (Blueprint $t) {
            $t->id();
            $t->string('scope_key', 64)->unique();
            $t->string('phone', 32);
            $t->string('purpose', 20);
            $t->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $t->string('code_hash');
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->timestamp('expires_at');
            $t->timestamp('used_at')->nullable();
            $t->timestamps();
        });
        Schema::create('admin_login_challenges', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('admin_id')->constrained()->cascadeOnDelete();
            $t->string('code_hash');
            $t->text('delivery_payload')->nullable();
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->unsignedTinyInteger('delivery_attempts')->default(0);
            $t->boolean('remember')->default(false);
            $t->timestamp('expires_at');
            $t->timestamp('used_at')->nullable();
            $t->timestamp('sent_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['admin_login_challenges', 'phone_challenges', 'sms_deliveries', 'integration_events', 'courier_remittances', 'courier_bookings', 'gateway_refunds', 'gateway_attempts', 'integration_connections'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('order_payments', function (Blueprint $t) {
            $t->dropUnique(['gateway', 'provider_reference']);
            $t->dropColumn(['gateway', 'provider_reference']);
        });
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn('advance_required'));
        // Keep admin_id nullable: verified historical receipts may have no staff actor.
    }
};
