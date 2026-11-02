<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hidden_product_tabs', function (Blueprint $t): void {
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_tab_id')->constrained()->cascadeOnDelete();
            $t->primary(['product_id', 'product_tab_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hidden_product_tabs');
    }
};
