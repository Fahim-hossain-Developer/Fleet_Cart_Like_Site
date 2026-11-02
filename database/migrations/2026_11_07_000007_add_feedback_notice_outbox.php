<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['reviews', 'questions'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->boolean('notice_pending')->default(false)->index();
                $t->unsignedTinyInteger('notice_attempts')->default(0);
            });
        }
    }

    public function down(): void
    {
        foreach (['reviews', 'questions'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropIndex(['notice_pending']);
                $t->dropColumn(['notice_pending', 'notice_attempts']);
            });
        }
    }
};
