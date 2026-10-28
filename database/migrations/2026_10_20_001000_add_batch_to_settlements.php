<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Settling up across trips writes one payment into every trip involved; the
 * batch ties them together so they are confirmed, or rejected, as one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settlements', function (Blueprint $table) {
            $table->string('batch', 16)->nullable()->after('collection_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('settlements', function (Blueprint $table) {
            $table->dropIndex(['batch']);
            $table->dropColumn('batch');
        });
    }
};
