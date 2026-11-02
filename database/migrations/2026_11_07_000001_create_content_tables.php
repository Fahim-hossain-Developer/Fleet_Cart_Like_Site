<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $t) {
            $t->id();
            $t->json('title');
            $t->json('body')->nullable();
            $t->json('excerpt')->nullable();
            $t->string('status', 16)->default('draft')->index();
            $t->boolean('is_active')->default(true);
            $t->timestamp('published_at')->nullable()->index();
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->unsignedInteger('sort')->default(0)->index();
            $t->timestamps();
            $t->string('slug', 160)->unique();
            $t->string('template', 32)->default('standard');
            $t->json('seo')->nullable();
        });
        Schema::create('post_categories', function (Blueprint $t) {
            $t->id();
            $t->json('title');
            $t->json('body')->nullable();
            $t->json('excerpt')->nullable();
            $t->string('status', 16)->default('draft')->index();
            $t->boolean('is_active')->default(true);
            $t->timestamp('published_at')->nullable()->index();
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->unsignedInteger('sort')->default(0)->index();
            $t->timestamps();
            $t->string('slug', 160)->unique();
        });
        Schema::create('posts', function (Blueprint $t) {
            $t->id();
            $t->json('title');
            $t->json('body')->nullable();
            $t->json('excerpt')->nullable();
            $t->string('status', 16)->default('draft')->index();
            $t->boolean('is_active')->default(true);
            $t->timestamp('published_at')->nullable()->index();
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->unsignedInteger('sort')->default(0)->index();
            $t->timestamps();
            $t->string('slug', 160)->unique();
            $t->foreignId('post_category_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $t->json('tags')->nullable();
            $t->json('seo')->nullable();
            $t->unsignedBigInteger('views')->default(0);
        });
        Schema::create('menus', function (Blueprint $t) {
            $t->id();
            $t->json('title');
            $t->json('body')->nullable();
            $t->json('excerpt')->nullable();
            $t->string('status', 16)->default('draft')->index();
            $t->boolean('is_active')->default(true);
            $t->timestamp('published_at')->nullable()->index();
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->unsignedInteger('sort')->default(0)->index();
            $t->timestamps();
            $t->string('key', 32)->unique();
        });
        Schema::create('menu_items', function (Blueprint $t) {
            $t->id();
            $t->json('title');
            $t->json('body')->nullable();
            $t->json('excerpt')->nullable();
            $t->string('status', 16)->default('draft')->index();
            $t->boolean('is_active')->default(true);
            $t->timestamp('published_at')->nullable()->index();
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->unsignedInteger('sort')->default(0)->index();
            $t->timestamps();
            $t->foreignId('menu_id')->constrained()->cascadeOnDelete();
            $t->foreignId('parent_id')->nullable()->constrained('menu_items')->nullOnDelete();
            $t->string('url', 2048);
            $t->boolean('new_tab')->default(false);
        });
        Schema::create('sliders', function (Blueprint $t) {
            $t->id();
            $t->json('title');
            $t->json('body')->nullable();
            $t->json('excerpt')->nullable();
            $t->string('status', 16)->default('draft')->index();
            $t->boolean('is_active')->default(true);
            $t->timestamp('published_at')->nullable()->index();
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->unsignedInteger('sort')->default(0)->index();
            $t->timestamps();
            $t->string('key', 64)->unique();
        });
        Schema::create('slides', function (Blueprint $t) {
            $t->id();
            $t->json('title');
            $t->json('body')->nullable();
            $t->json('excerpt')->nullable();
            $t->string('status', 16)->default('draft')->index();
            $t->boolean('is_active')->default(true);
            $t->timestamp('published_at')->nullable()->index();
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->unsignedInteger('sort')->default(0)->index();
            $t->timestamps();
            $t->foreignId('slider_id')->constrained()->cascadeOnDelete();
            $t->string('image')->nullable();
            $t->string('mobile_image')->nullable();
            $t->string('url', 2048)->nullable();
            $t->json('button_label')->nullable();
        });
        Schema::create('banners', function (Blueprint $t) {
            $t->id();
            $t->json('title');
            $t->json('body')->nullable();
            $t->json('excerpt')->nullable();
            $t->string('status', 16)->default('draft')->index();
            $t->boolean('is_active')->default(true);
            $t->timestamp('published_at')->nullable()->index();
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->unsignedInteger('sort')->default(0)->index();
            $t->timestamps();
            $t->string('image')->nullable();
            $t->string('mobile_image')->nullable();
            $t->string('url', 2048)->nullable();
            $t->string('position', 32)->default('home');
        });
        Schema::create('home_sections', function (Blueprint $t) {
            $t->id();
            $t->json('title');
            $t->json('body')->nullable();
            $t->json('excerpt')->nullable();
            $t->string('status', 16)->default('draft')->index();
            $t->boolean('is_active')->default(true);
            $t->timestamp('published_at')->nullable()->index();
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->unsignedInteger('sort')->default(0)->index();
            $t->timestamps();
            $t->string('type', 40);
            $t->json('config')->nullable();
            $t->string('locale', 2)->nullable();
        });
        Schema::create('faq_entries', function (Blueprint $t) {
            $t->id();
            $t->json('title');
            $t->json('body')->nullable();
            $t->json('excerpt')->nullable();
            $t->string('status', 16)->default('draft')->index();
            $t->boolean('is_active')->default(true);
            $t->timestamp('published_at')->nullable()->index();
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->unsignedInteger('sort')->default(0)->index();
            $t->timestamps();
            $t->string('group', 64)->default('general');
        });
        Schema::create('popups', function (Blueprint $t) {
            $t->id();
            $t->json('title');
            $t->json('body')->nullable();
            $t->json('excerpt')->nullable();
            $t->string('status', 16)->default('draft')->index();
            $t->boolean('is_active')->default(true);
            $t->timestamp('published_at')->nullable()->index();
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->unsignedInteger('sort')->default(0)->index();
            $t->timestamps();
            $t->string('image')->nullable();
            $t->string('url', 2048)->nullable();
            $t->string('trigger', 20)->default('delay');
            $t->unsignedSmallInteger('delay_seconds')->default(15);
            $t->unsignedSmallInteger('frequency_hours')->default(168);
            $t->json('paths')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('popups');
        Schema::dropIfExists('faq_entries');
        Schema::dropIfExists('home_sections');
        Schema::dropIfExists('banners');
        Schema::dropIfExists('slides');
        Schema::dropIfExists('sliders');
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('menus');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('post_categories');
        Schema::dropIfExists('pages');
    }
};
