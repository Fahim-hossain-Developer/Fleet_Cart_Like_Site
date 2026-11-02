<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t): void {
            $t->string('email')->nullable()->change();
            $t->string('phone', 20)->nullable()->unique();
            $t->timestamp('phone_verified_at')->nullable();
            $t->string('locale', 5)->default('en');
            $t->string('currency', 3)->default('BDT');
        });
        Schema::create('locations', function (Blueprint $t): void {
            $t->id();
            $t->string('code')->unique();
            $t->string('country', 2)->default('BD');
            $t->string('type');
            $t->foreignId('parent_id')->nullable()->constrained('locations')->cascadeOnDelete();
            $t->json('name');
            $t->timestamps();
        });
        Schema::create('shipping_zones', function (Blueprint $t): void {
            $t->id();
            $t->json('name');
            $t->string('country', 2)->default('BD');
            $t->json('district_ids');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('shipping_methods', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('shipping_zone_id')->constrained()->cascadeOnDelete();
            $t->json('name');
            $t->string('type')->default('flat');
            $t->unsignedBigInteger('amount')->default(0);
            $t->unsignedBigInteger('free_above')->nullable();
            $t->unsignedInteger('base_weight_grams')->default(1000);
            $t->unsignedInteger('step_weight_grams')->default(1000);
            $t->unsignedBigInteger('step_amount')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('tax_classes', function (Blueprint $t): void {
            $t->id();
            $t->json('name');
            $t->timestamps();
        });
        Schema::create('tax_rates', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('tax_class_id')->constrained()->cascadeOnDelete();
            $t->string('country', 2)->default('BD');
            $t->foreignId('district_id')->nullable()->constrained('locations')->nullOnDelete();
            $t->unsignedInteger('rate_bps')->default(0);
            $t->boolean('inclusive')->default(false);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::table('products', function (Blueprint $t): void {
            $t->foreignId('tax_class_id')->nullable()->constrained()->nullOnDelete();
            $t->unsignedInteger('weight_grams')->default(0);
            $t->unsignedBigInteger('gift_fee')->nullable();
        });
        Schema::table('categories', fn (Blueprint $t) => $t->unsignedBigInteger('gift_fee')->nullable());
        Schema::create('currencies', function (Blueprint $t): void {
            $t->id();
            $t->string('code', 3)->unique();
            $t->string('symbol', 10);
            $t->unsignedBigInteger('rate_ppm')->default(1000000);
            $t->unsignedInteger('decimals')->default(2);
            $t->string('symbol_position')->default('before');
            $t->unsignedInteger('rounding_increment')->default(1);
            $t->unsignedInteger('markup_bps')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('currency_rate_history', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('currency_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('rate_ppm');
            $t->string('source')->default('manual');
            $t->timestamp('fetched_at');
        });
        Schema::create('coupons', function (Blueprint $t): void {
            $t->id();
            $t->string('code')->unique();
            $t->string('type')->default('fixed');
            $t->unsignedBigInteger('value');
            $t->unsignedBigInteger('minimum')->default(0);
            $t->unsignedBigInteger('max_discount')->nullable();
            $t->unsignedInteger('usage_limit')->nullable();
            $t->unsignedInteger('per_customer_limit')->nullable();
            $t->unsignedInteger('used')->default(0);
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('carts', function (Blueprint $t): void {
            $t->id();
            $t->string('token', 64)->unique();
            $t->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('cart_items', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $t->string('line_key', 64);
            $t->unsignedInteger('qty');
            $t->json('options');
            $t->boolean('saved')->default(false);
            $t->timestamps();
            $t->unique(['cart_id', 'line_key']);
        });
        Schema::create('orders', function (Blueprint $t): void {
            $t->id();
            $t->string('number')->unique();
            $t->string('idempotency_key', 64)->unique();
            $t->foreignId('cart_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('phone', 20)->index();
            $t->string('guest_email')->nullable();
            $t->string('status')->default('pending');
            $t->string('payment_status')->default('unpaid');
            $t->string('fulfillment_status')->default('unfulfilled');
            $t->string('currency', 3);
            $t->string('display_currency', 3);
            $t->unsignedBigInteger('display_rate_ppm')->default(1000000);
            $t->unsignedBigInteger('display_total');
            foreach (['subtotal', 'discount', 'shipping', 'tax', 'gift_fee', 'total', 'paid_amount'] as $key) {
                $t->unsignedBigInteger($key)->default(0);
            } $t->boolean('is_gift')->default(false);
            $t->text('gift_message')->nullable();
            $t->boolean('hide_prices')->default(false);
            $t->string('recipient_name')->nullable();
            $t->string('recipient_phone', 20)->nullable();
            $t->json('shipping_address');
            $t->text('note')->nullable();
            $t->string('payment_method');
            $t->string('payment_reference')->nullable();
            $t->string('payment_proof')->nullable();
            $t->string('shipping_method');
            $t->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamp('placed_at');
            $t->timestamps();
        });
        Schema::create('order_items', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->restrictOnDelete();
            $t->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $t->string('name');
            $t->string('sku');
            $t->json('options');
            $t->unsignedInteger('qty');
            foreach (['unit_price', 'subtotal', 'discount', 'tax', 'total'] as $key) {
                $t->unsignedBigInteger($key);
            } $t->timestamps();
        });
        Schema::create('coupon_usages', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $t->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $t->string('identity_hash', 64)->index();
            $t->timestamps();
        });
        Schema::create('order_events', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->string('type');
            $t->text('note')->nullable();
            $t->boolean('is_public')->default(false);
            $t->timestamps();
        });
        Schema::create('account_setup_tokens', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('token_hash', 64)->unique();
            $t->timestamp('expires_at');
            $t->timestamp('used_at')->nullable();
            $t->timestamps();
        });
        Schema::create('order_notifications', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $t->timestamp('sent_at')->nullable();
            $t->unsignedInteger('attempts')->default(0);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['order_notifications', 'account_setup_tokens', 'order_events', 'coupon_usages', 'order_items', 'orders', 'cart_items', 'carts', 'coupons', 'currency_rate_history', 'currencies'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('products', function (Blueprint $t): void {
            $t->dropConstrainedForeignId('tax_class_id');
            $t->dropColumn(['weight_grams', 'gift_fee']);
        });
        Schema::table('categories', fn (Blueprint $t) => $t->dropColumn('gift_fee'));
        foreach (['tax_rates', 'tax_classes', 'shipping_methods', 'shipping_zones', 'locations'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', fn (Blueprint $t) => $t->dropUnique('users_phone_unique'));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['phone', 'phone_verified_at', 'locale', 'currency']));
    }
};
