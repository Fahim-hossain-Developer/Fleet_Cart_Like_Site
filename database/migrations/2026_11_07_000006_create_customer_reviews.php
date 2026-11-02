<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('order_item_id')->nullable()->constrained()->nullOnDelete();
            $t->string('display_name', 50);
            $t->unsignedTinyInteger('rating');
            $t->unsignedInteger('revision')->default(1);
            $t->string('title', 120);
            $t->text('body');
            $t->json('images')->nullable();
            $t->boolean('is_verified')->default(false);
            $t->string('status', 16)->default('pending')->index();
            $t->text('admin_reply')->nullable();
            $t->foreignId('moderated_by')->nullable()->constrained('admins')->nullOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->timestamps();
            $t->unique(['product_id', 'user_id']);
        });
        Schema::create('review_votes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('review_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['review_id', 'user_id']);
        });
        Schema::create('questions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('display_name', 50);
            $t->text('question');
            $t->text('answer')->nullable();
            $t->string('status', 16)->default('pending')->index();
            $t->foreignId('answered_by')->nullable()->constrained('admins')->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('review_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $t->timestamp('sent_at')->nullable();
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->timestamps();
        });
        Schema::create('saved_coupons', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['user_id', 'coupon_id']);
        });
        Schema::table('coupons', fn (Blueprint $t) => $t->boolean('is_public')->default(false));
    }

    public function down(): void
    {
        Schema::table('coupons', fn (Blueprint $t) => $t->dropColumn('is_public'));
        foreach (['saved_coupons', 'review_requests', 'questions', 'review_votes', 'reviews'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
