<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_subscribers', function (Blueprint $t) {
            $t->id();
            $t->string('email_hash', 64)->unique();
            $t->text('email');
            $t->string('status', 20)->default('pending')->index();
            $t->string('source', 20)->default('storefront');
            $t->string('locale', 2)->default('en');
            $t->unsignedInteger('generation')->default(0);
            $t->string('confirmation_hash', 64)->nullable()->unique();
            $t->timestamp('confirmation_expires_at')->nullable();
            $t->string('unsubscribe_hash', 64)->nullable()->unique();
            $t->text('unsubscribe_token')->nullable();
            $t->timestamp('consented_at')->nullable();
            $t->timestamp('confirmed_at')->nullable();
            $t->timestamp('unsubscribed_at')->nullable();
            $t->timestamps();
        });
        Schema::create('newsletter_campaigns', function (Blueprint $t) {
            $t->id();
            $t->json('name');
            $t->json('body');
            $t->string('status', 20)->default('draft')->index();
            $t->timestamp('send_at')->nullable()->index();
            $t->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $t->timestamp('prepared_at')->nullable();
            $t->timestamp('audience_at')->nullable();
            $t->timestamps();
        });
        Schema::create('newsletter_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('newsletter_subscriber_id')->constrained()->cascadeOnDelete();
            $t->foreignId('newsletter_campaign_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('kind', 20);
            $t->unsignedInteger('generation');
            $t->text('payload')->nullable();
            $t->string('status', 20)->default('pending')->index();
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->string('failure_code', 64)->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamp('sent_at')->nullable();
            $t->timestamps();
            $t->unique(['newsletter_campaign_id', 'newsletter_subscriber_id'], 'campaign_subscriber_unique');
        });
    }

    public function down(): void
    {
        foreach (['newsletter_messages', 'newsletter_campaigns', 'newsletter_subscribers'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
