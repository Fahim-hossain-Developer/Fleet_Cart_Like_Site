<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopping_lists', function (Blueprint $t) {
            $t->id();
            $t->string('owner_key', 80)->unique();
            $t->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $t->timestamps();
        });
        Schema::create('shopping_list_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('shopping_list_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->string('kind', 12);
            $t->timestamps();
            $t->unique(['shopping_list_id', 'product_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopping_list_items');
        Schema::dropIfExists('shopping_lists');
    }
};
