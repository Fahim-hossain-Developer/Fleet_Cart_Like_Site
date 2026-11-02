<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', fn (Blueprint $t) => $t->unsignedBigInteger('tax_included')->default(0));
        Schema::table('order_items', fn (Blueprint $t) => $t->boolean('tax_inclusive')->default(false));
    }

    public function down(): void
    {
        Schema::table('order_items', fn (Blueprint $t) => $t->dropColumn('tax_inclusive'));
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn('tax_included'));
    }
};
