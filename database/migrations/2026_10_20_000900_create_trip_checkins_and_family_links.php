<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Peace of mind for the people at home: travellers check in ("Reached Sajek",
 * "Safe at the cottage") and each traveller can share a private link with
 * their family that shows the trip and its check-ins, no account needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_checkins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trip_member_id')->nullable()->constrained('trip_members')->nullOnDelete();
            $table->string('place', 80);
            $table->string('note', 160)->nullable();
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
            $table->timestamps();

            $table->index(['trip_id', 'id']);
        });

        Schema::table('trip_members', function (Blueprint $table) {
            $table->string('family_token', 24)->nullable()->unique()->after('pay_token');
            // Texted on check-ins when the traveller asks, from their SMS wallet.
            $table->string('family_phone', 20)->nullable()->after('family_token');
        });
    }

    public function down(): void
    {
        Schema::table('trip_members', function (Blueprint $table) {
            $table->dropUnique(['family_token']);
            $table->dropColumn(['family_token', 'family_phone']);
        });

        Schema::dropIfExists('trip_checkins');
    }
};
