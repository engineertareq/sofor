<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settlements', function (Blueprint $table) {
            // Gateway payments keep their own audit trail next to the ledger row.
            $table->string('gateway', 32)->nullable()->after('method');
            $table->string('gateway_trx_id', 64)->nullable()->unique()->after('gateway');
            $table->decimal('charge', 12, 2)->default(0)->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('settlements', function (Blueprint $table) {
            $table->dropUnique(['gateway_trx_id']);
            $table->dropColumn(['gateway', 'gateway_trx_id', 'charge']);
        });
    }
};
