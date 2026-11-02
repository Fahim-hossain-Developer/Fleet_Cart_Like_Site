<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->boolean('is_blocked')->default(false);
            $t->text('staff_notes')->nullable();
            $t->json('tags')->nullable();
            $t->timestamp('last_login_at')->nullable();
        });
        Schema::table('orders', function (Blueprint $t) {
            $t->string('stock_policy')->default('placement');
            $t->unsignedBigInteger('refunded_amount')->default(0);
            $t->string('ip_hash', 64)->nullable()->index();
            $t->json('risk_flags')->nullable();
        });
        Schema::table('order_items', function (Blueprint $t) {
            $t->unsignedInteger('stock_deducted')->default(0);
            $t->unsignedInteger('restocked_qty')->default(0);
            $t->unsignedInteger('tax_bps')->default(0);
        });
        // Existing checkout orders already deducted physical inventory on placement.
        DB::table('order_items')->orderBy('id')->chunkById(100, function ($items) {
            foreach ($items as $item) {
                $p = DB::table('products')->find($item->product_id);
                if ($item->variant_id || ($p && $p->manage_stock && $p->stock_status !== 'backorder')) {
                    DB::table('order_items')->where('id', $item->id)->update(['stock_deducted' => $item->qty]);
                }
            }
        });
        Schema::table('order_events', function (Blueprint $t) {
            $t->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
        });
        Schema::create('order_payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->string('key', 64)->unique();
            $t->string('reference', 150);
            $t->unsignedBigInteger('amount');
            $t->foreignId('admin_id')->constrained('admins');
            $t->timestamps();
        });
        Schema::create('shipments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->string('key', 64)->unique();
            $t->string('carrier', 100);
            $t->string('tracking_no', 150);
            $t->json('items');
            $t->string('status')->default('shipped');
            $t->timestamps();
        });
        Schema::create('order_returns', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->json('items');
            $t->text('reason');
            $t->string('status')->default('requested');
            $t->text('staff_note')->nullable();
            $t->timestamps();
        });
        Schema::create('order_refunds', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->string('key', 64)->unique();
            $t->unsignedBigInteger('amount');
            $t->text('reason');
            $t->json('items');
            $t->foreignId('admin_id')->constrained('admins');
            $t->timestamps();
        });
        Schema::create('phone_blacklist', function (Blueprint $t) {
            $t->id();
            $t->string('phone', 32)->unique();
            $t->string('reason', 255);
            $t->timestamps();
        });
        Schema::create('addresses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('name', 100);
            $t->string('phone', 32);
            $t->string('country', 2)->default('BD');
            $t->foreignId('division_id')->constrained('locations');
            $t->foreignId('district_id')->constrained('locations');
            $t->foreignId('upazila_id')->constrained('locations');
            $t->foreignId('area_id')->nullable()->constrained('locations');
            $t->string('line1', 500);
            $t->string('area', 100)->nullable();
            $t->boolean('is_default')->default(false);
            $t->timestamps();
        });
        Schema::create('otp_codes', function (Blueprint $t) {
            $t->id();
            $t->string('identifier', 254)->unique();
            $t->string('code_hash');
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->timestamp('expires_at');
            $t->timestamp('used_at')->nullable();
            $t->timestamps();
        });
        Schema::create('download_entitlements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_download_id')->constrained()->cascadeOnDelete();
            $t->string('label');
            $t->string('file')->nullable();
            $t->string('external_url', 2048)->nullable();
            $t->unsignedInteger('limit')->nullable();
            $t->unsignedInteger('used')->default(0);
            $t->unsignedInteger('expiry_days')->nullable();
            $t->timestamp('enabled_at')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();
            $t->unique(['order_item_id', 'product_download_id']);
        });
    }

    public function down(): void
    {
        foreach (['download_entitlements', 'otp_codes', 'addresses', 'phone_blacklist', 'order_refunds', 'order_returns', 'shipments', 'order_payments'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('order_events', function (Blueprint $t) {
            $t->dropConstrainedForeignId('admin_id');
        });
        Schema::table('order_items', fn (Blueprint $t) => $t->dropColumn(['stock_deducted', 'restocked_qty', 'tax_bps']));
        Schema::table('orders', function (Blueprint $t) {
            $t->dropIndex(['ip_hash']);
            $t->dropColumn(['stock_policy', 'refunded_amount', 'ip_hash', 'risk_flags']);
        });
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['is_blocked', 'staff_notes', 'tags', 'last_login_at']));
    }
};
