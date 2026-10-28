<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Costs logged without a connection are queued on the phone and sent when it
 * is back. Each carries an id made on the phone, so a send that is retried
 * after a dropped connection never logs the same cost twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->uuid('client_uuid')->nullable()->after('items');
            $table->unique(['trip_id', 'client_uuid']);
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropUnique(['trip_id', 'client_uuid']);
            $table->dropColumn('client_uuid');
        });
    }
};
