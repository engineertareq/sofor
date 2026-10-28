<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Profile photos are cropped by their owner: the whole photo is kept (resized,
 * cleaned of camera data) beside the square that is shown, with the square
 * that was chosen, so it can be moved or zoomed again later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar_original_path')->nullable()->after('avatar_path');
            $table->json('avatar_crop')->nullable()->after('avatar_original_path');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['avatar_original_path', 'avatar_crop']);
        });
    }
};
