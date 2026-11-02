<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gateway_refunds', fn (Blueprint $t) => $t->string('gateway', 52)->nullable());
        DB::table('gateway_refunds')->join('gateway_attempts', 'gateway_attempts.id', '=', 'gateway_refunds.gateway_attempt_id')->select(['gateway_refunds.id', 'gateway_attempts.driver', 'gateway_refunds.mode'])->orderBy('gateway_refunds.id')->chunk(100, function ($rows) {
            foreach ($rows as $row) {
                DB::table('gateway_refunds')->where('id', $row->id)->update(['gateway' => $row->driver.':'.$row->mode]);
            }
        });
        Schema::table('gateway_refunds', fn (Blueprint $t) => $t->unique(['gateway', 'provider_reference']));
    }

    public function down(): void
    {
        Schema::table('gateway_refunds', function (Blueprint $t) {
            $t->dropUnique(['gateway', 'provider_reference']);
            $t->dropColumn('gateway');
        });
    }
};
