<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('theme_presets', function (Blueprint $t) {
            $t->id();
            $t->string('slug', 100)->unique();
            $t->json('name');
            $t->json('config');
            $t->boolean('is_system')->default(false);
            $t->timestamps();
        });
        Schema::create('theme_drafts', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('admin_id')->constrained()->cascadeOnDelete();
            $t->json('config');
            $t->unsignedInteger('base_revision');
            $t->timestamp('expires_at')->index();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('theme_drafts');
        Schema::dropIfExists('theme_presets');
    }
};
