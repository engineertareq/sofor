<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A photo of the bill on each cost (private media), and the line items of an
 * itemised split: [{name, price, members: [trip_member_id, ...]}].
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->string('receipt_path')->nullable()->after('notes');
            $table->json('items')->nullable()->after('receipt_path');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn(['receipt_path', 'items']);
        });
    }
};
