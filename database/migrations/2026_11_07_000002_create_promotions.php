<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flash_sales', function (Blueprint $t) {
            $t->id();
            $t->json('name');
            $t->boolean('is_active')->default(false);
            $t->timestamp('starts_at')->index();
            $t->timestamp('ends_at')->index();
            $t->timestamps();
        });
        Schema::create('flash_sale_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('flash_sale_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->restrictOnDelete();
            $t->foreignId('variant_id')->nullable()->constrained('product_variants')->restrictOnDelete();
            $t->unsignedBigInteger('variant_key')->default(0);
            $t->unsignedBigInteger('price');
            $t->unsignedInteger('quota');
            $t->unsignedInteger('sold')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['flash_sale_id', 'product_id', 'variant_key'], 'flash_sale_sku_unique');
        });
        Schema::create('flash_allocations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_item_id')->unique()->constrained()->restrictOnDelete();
            $t->foreignId('flash_sale_item_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('qty');
            $t->unsignedInteger('released')->default(0);
            $t->timestamps();
        });
        Schema::create('cart_rules', function (Blueprint $t) {
            $t->id();
            $t->json('name');
            $t->boolean('is_active')->default(false);
            $t->unsignedInteger('priority')->default(100)->index();
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->unsignedBigInteger('minimum')->default(0);
            $t->unsignedInteger('minimum_qty')->default(1);
            $t->string('type', 24);
            $t->unsignedBigInteger('value')->default(0);
            $t->unsignedBigInteger('max_discount')->nullable();
            $t->boolean('stackable')->default(false);
            $t->timestamps();
        });
        Schema::table('orders', function (Blueprint $t) {
            $t->unsignedBigInteger('automatic_discount')->default(0);
            $t->unsignedBigInteger('coupon_discount')->default(0);
            $t->json('cart_rule_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn(['automatic_discount', 'coupon_discount', 'cart_rule_snapshot']));
        foreach (['cart_rules', 'flash_allocations', 'flash_sale_items', 'flash_sales'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
