<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A trip's cost timeline can be shared at sofor.io/timeline/{token}. The
 * leader turns the link on and off (timeline_shared_at); the token survives
 * switching it off, so turning it back on brings the same link back, until
 * the leader asks for a new one. timeline_names: whether it says who paid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->string('timeline_token', 40)->nullable()->unique()->after('invite_code');
            $table->timestamp('timeline_shared_at')->nullable()->after('timeline_token');
            $table->boolean('timeline_names')->default(true)->after('timeline_shared_at');
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropUnique(['timeline_token']);
            $table->dropColumn(['timeline_token', 'timeline_shared_at', 'timeline_names']);
        });
    }
};
