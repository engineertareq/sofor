<?php

use App\Support\Username;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // The handle behind sofor.io/@username; always stored lowercase.
            $table->string('username', 30)->nullable()->after('name');
            // Travellers can hide their public profile; it is shown by default.
            $table->boolean('profile_public')->default(true)->after('avatar_path');
        });

        // Accounts made before usernames existed get one from their name; they
        // can change it from their profile.
        DB::table('users')->whereNull('username')->orderBy('id')->eachById(function (object $user) {
            DB::table('users')->where('id', $user->id)->update([
                'username' => Username::suggest($user->name, $user->email),
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 30)->nullable(false)->change();
            $table->unique('username');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn(['username', 'profile_public']);
        });
    }
};
