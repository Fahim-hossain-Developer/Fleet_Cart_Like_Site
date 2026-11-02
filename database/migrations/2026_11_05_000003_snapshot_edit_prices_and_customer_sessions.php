<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->unsignedInteger('auth_version')->default(0));
        Schema::table('order_items', function (Blueprint $t) {
            $t->unsignedInteger('pricing_qty')->nullable();
            $t->unsignedBigInteger('pricing_subtotal')->nullable();
        });
        Schema::table('orders', fn (Blueprint $t) => $t->unsignedBigInteger('original_discount')->nullable());
        DB::table('order_items')->update(['pricing_qty' => DB::raw('qty'), 'pricing_subtotal' => DB::raw('subtotal')]);
        DB::table('orders')->update(['original_discount' => DB::raw('discount')]);
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn('original_discount'));
        Schema::table('order_items', fn (Blueprint $t) => $t->dropColumn(['pricing_qty', 'pricing_subtotal']));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('auth_version'));
    }
};
