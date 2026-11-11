<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the Android app needs from the database: sign-in tokens (Sanctum), the
 * phones to push alerts to, payments that can be retried safely from a phone
 * with bad signal, and accounts that were deleted by their owner.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // FCM registration tokens are long and belong to one install of the app.
            $table->string('token', 512);
            $table->string('token_hash', 64)->unique();
            $table->string('platform', 16)->default('android');
            $table->string('app_version', 32)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        Schema::table('settlements', function (Blueprint $table) {
            $table->uuid('client_uuid')->nullable()->after('batch');
            $table->unique(['trip_id', 'client_uuid']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('deleted_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('deleted_at');
        });

        Schema::table('settlements', function (Blueprint $table) {
            $table->dropUnique(['trip_id', 'client_uuid']);
            $table->dropColumn('client_uuid');
        });

        Schema::dropIfExists('device_tokens');
        Schema::dropIfExists('personal_access_tokens');
    }
};
