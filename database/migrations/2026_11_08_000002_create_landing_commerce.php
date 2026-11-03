<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landing_pages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('slug', 160)->unique();
            $t->json('title');
            $t->json('body')->nullable();
            $t->json('excerpt')->nullable();
            $t->json('sections');
            $t->json('form_config');
            $t->json('theme_config')->nullable();
            $t->json('seo')->nullable();
            $t->string('status', 16)->default('draft')->index();
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('sort')->default(0);
            $t->timestamp('published_at')->nullable();
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->unsignedBigInteger('views')->default(0);
            $t->unsignedBigInteger('orders_count')->default(0);
            $t->timestamps();
        });
        Schema::create('landing_templates', function (Blueprint $t) {
            $t->id();
            $t->string('slug', 160)->unique();
            $t->json('name');
            $t->json('sections');
            $t->json('form_config');
            $t->string('thumbnail')->nullable();
            $t->boolean('is_system')->default(false);
            $t->timestamps();
        });
        Schema::create('product_packages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('landing_page_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->restrictOnDelete();
            $t->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $t->json('label');
            $t->string('role', 16)->default('primary');
            $t->unsignedSmallInteger('qty')->default(1);
            $t->unsignedBigInteger('price');
            $t->boolean('free_shipping')->default(false);
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('sort')->default(0);
            $t->timestamps();
        });
        Schema::table('carts', function (Blueprint $t) {
            $t->foreignId('landing_page_id')->nullable()->constrained()->nullOnDelete();
            $t->json('landing_context')->nullable();
        });
        Schema::table('cart_items', function (Blueprint $t) {
            $t->foreignId('product_package_id')->nullable()->constrained()->nullOnDelete();
            $t->unsignedSmallInteger('package_units')->default(0);
        });
        Schema::table('orders', function (Blueprint $t) {
            $t->foreignId('landing_page_id')->nullable()->constrained()->nullOnDelete();
            $t->json('landing_snapshot')->nullable();
            $t->text('checkout_meta')->nullable();
        });
        Schema::table('order_items', function (Blueprint $t) {
            $t->json('package_snapshot')->nullable();
        });
        Schema::create('checkout_phone_challenges', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $t->string('phone_hash', 64)->index();
            $t->string('code_hash')->nullable();
            $t->foreignId('sms_delivery_id')->nullable()->constrained()->nullOnDelete();
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->timestamp('expires_at');
            $t->timestamp('verified_at')->nullable();
            $t->timestamp('used_at')->nullable();
            $t->timestamps();
        });
        Schema::create('landing_order_claims', function (Blueprint $t) {
            $t->id();
            $t->string('identity_hash', 64)->unique();
            $t->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landing_order_claims');
        Schema::dropIfExists('checkout_phone_challenges');
        Schema::table('order_items', fn (Blueprint $t) => $t->dropColumn('package_snapshot'));
        Schema::table('orders', function (Blueprint $t) {
            $t->dropConstrainedForeignId('landing_page_id');
            $t->dropColumn(['landing_snapshot', 'checkout_meta']);
        });
        Schema::table('cart_items', function (Blueprint $t) {
            $t->dropConstrainedForeignId('product_package_id');
            $t->dropColumn('package_units');
        });
        Schema::table('carts', function (Blueprint $t) {
            $t->dropConstrainedForeignId('landing_page_id');
            $t->dropColumn('landing_context');
        });
        Schema::dropIfExists('product_packages');
        Schema::dropIfExists('landing_templates');
        Schema::dropIfExists('landing_pages');
    }
};
