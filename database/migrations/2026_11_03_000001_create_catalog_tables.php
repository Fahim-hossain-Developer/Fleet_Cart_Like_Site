<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['brands', 'tags', 'attribute_sets'] as $name) {
            Schema::create($name, function (Blueprint $t) {
                $t->id();
                $t->string('slug')->unique();
                $t->json('name');
                $t->json('description')->nullable();
                $t->string('image')->nullable();
                $t->json('seo')->nullable();
                $t->boolean('is_active')->default(true);
                $t->timestamps();
            });
        }
        Schema::create('categories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $t->string('slug')->unique();
            $t->json('name');
            $t->json('description')->nullable();
            $t->string('image')->nullable();
            $t->string('banner')->nullable();
            $t->json('seo')->nullable();
            $t->unsignedInteger('sort')->default(0);
            $t->boolean('is_active')->default(true);
            $t->boolean('show_in_menu')->default(true);
            $t->timestamps();
        });
        Schema::create('attributes', function (Blueprint $t) {
            $t->id();
            $t->string('slug')->unique();
            $t->json('name');
            $t->string('type')->default('text');
            $t->boolean('is_filterable')->default(true);
            $t->timestamps();
        });
        Schema::create('attribute_values', function (Blueprint $t) {
            $t->id();
            $t->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $t->json('name');
            $t->string('value');
            $t->string('image')->nullable();
            $t->unsignedInteger('sort')->default(0);
            $t->timestamps();
            $t->unique(['attribute_id', 'value']);
        });
        Schema::create('attribute_attribute_set', function (Blueprint $t) {
            $t->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $t->foreignId('attribute_set_id')->constrained()->cascadeOnDelete();
            $t->primary(['attribute_id', 'attribute_set_id']);
        });
        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->string('sku')->unique();
            $t->string('slug')->unique();
            $t->json('name');
            $t->json('short_desc')->nullable();
            $t->json('description')->nullable();
            $t->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('attribute_set_id')->nullable()->constrained()->nullOnDelete();
            $t->string('type')->default('simple');
            $t->string('status')->default('draft')->index();
            $t->string('visibility')->default('visible');
            $t->unsignedBigInteger('price')->default(0)->index();
            $t->unsignedBigInteger('special_price')->nullable();
            $t->timestamp('special_from')->nullable();
            $t->timestamp('special_to')->nullable();
            $t->unsignedBigInteger('cost')->nullable();
            $t->boolean('manage_stock')->default(true);
            $t->unsignedInteger('qty')->default(0);
            $t->string('stock_status')->default('in_stock');
            $t->unsignedInteger('low_stock_at')->default(5);
            $t->unsignedInteger('max_per_order')->nullable();
            $t->boolean('is_featured')->default(false);
            $t->timestamp('new_until')->nullable();
            $t->boolean('free_shipping')->default(false);
            $t->boolean('is_preorder')->default(false);
            $t->string('video_url')->nullable();
            $t->json('seo')->nullable();
            $t->unsignedBigInteger('views')->default(0);
            $t->unsignedInteger('rating_count')->default(0);
            $t->decimal('rating_avg', 3, 2)->default(0);
            $t->text('search_text')->nullable();
            $t->softDeletes();
            $t->timestamps();
        });
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            Schema::table('products', fn (Blueprint $t) => $t->fullText('search_text'));
        }
        foreach (['category', 'tag'] as $related) {
            Schema::create($related.'_product', function (Blueprint $t) use ($related) {
                $t->foreignId('product_id')->constrained()->cascadeOnDelete();
                $t->foreignId($related.'_id')->constrained()->cascadeOnDelete();
                $t->primary(['product_id', $related.'_id']);
            });
        }
        Schema::create('attribute_value_product', function (Blueprint $t) {
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->foreignId('attribute_value_id')->constrained()->cascadeOnDelete();
            $t->primary(['product_id', 'attribute_value_id']);
        });
        Schema::create('product_variants', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->string('sku')->unique();
            $t->json('attributes');
            $t->unsignedBigInteger('price');
            $t->unsignedBigInteger('special_price')->nullable();
            $t->unsignedInteger('qty')->default(0);
            $t->string('image')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('product_options', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->json('label');
            $t->string('type');
            $t->boolean('required')->default(false);
            $t->unsignedBigInteger('price')->default(0);
            $t->string('price_type')->default('fixed');
            $t->unsignedInteger('sort')->default(0);
            $t->timestamps();
        });
        Schema::create('option_values', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_option_id')->constrained()->cascadeOnDelete();
            $t->json('label');
            $t->unsignedBigInteger('price')->default(0);
            $t->unsignedInteger('sort')->default(0);
            $t->timestamps();
        });
        Schema::create('product_downloads', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->string('file')->nullable();
            $t->string('external_url')->nullable();
            $t->unsignedInteger('limit')->nullable();
            $t->unsignedInteger('expiry_days')->nullable();
            $t->timestamps();
        });
        Schema::create('product_relations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->foreignId('related_id')->constrained('products')->cascadeOnDelete();
            $t->string('type')->default('related');
            $t->timestamps();
            $t->unique(['product_id', 'related_id', 'type']);
        });
        Schema::create('product_tabs', function (Blueprint $t) {
            $t->id();
            $t->string('scope')->default('product');
            $t->foreignId('product_id')->nullable()->constrained()->cascadeOnDelete();
            $t->json('category_ids')->nullable();
            $t->json('title');
            $t->json('body');
            $t->unsignedInteger('sort')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('badges', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();
            $t->json('label');
            $t->string('color')->default('#065f46');
            $t->string('icon')->nullable();
            $t->json('rule')->nullable();
            $t->unsignedInteger('priority')->default(50);
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        foreach (['product', 'category'] as $target) {
            Schema::create('badge_'.$target, function (Blueprint $t) use ($target) {
                $t->foreignId('badge_id')->constrained()->cascadeOnDelete();
                $t->foreignId($target.'_id')->constrained()->cascadeOnDelete();
                $t->primary(['badge_id', $target.'_id']);
            });
        }
        Schema::create('redirects', function (Blueprint $t) {
            $t->id();
            $t->string('from')->unique();
            $t->string('to');
            $t->unsignedSmallInteger('code')->default(301);
            $t->timestamps();
        });
        Schema::create('search_logs', function (Blueprint $t) {
            $t->id();
            $t->string('term', 100);
            $t->unsignedInteger('results');
            $t->timestamps();
        });
        Schema::create('catalog_imports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('admin_id')->constrained()->cascadeOnDelete();
            $t->string('path');
            $t->string('status')->default('queued');
            $t->unsignedInteger('imported')->default(0);
            $t->json('errors')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['catalog_imports', 'search_logs', 'redirects', 'badge_category', 'badge_product', 'badges', 'product_tabs', 'product_relations', 'product_downloads', 'option_values', 'product_options', 'product_variants', 'attribute_value_product', 'category_product', 'tag_product', 'products', 'attribute_attribute_set', 'attribute_values', 'attributes', 'categories', 'attribute_sets', 'tags', 'brands'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
