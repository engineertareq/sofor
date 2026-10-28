<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The checkpoint list: the ID details army and police checkpoints (Sajek,
 * Bandarban, Rangamati...) and hotels ask for. Everything is stored
 * encrypted, seen only by the traveller and the trip leader, and purged a
 * couple of weeks after the trip ends.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('traveller_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_member_id')->unique()->constrained('trip_members')->cascadeOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            // Encrypted JSON: full_name, id_type, id_number, date_of_birth, gender,
            // phone, address, blood_group, emergency_name, emergency_phone.
            $table->text('data');
            $table->timestamps();
        });

        Schema::table('trips', function (Blueprint $table) {
            // Encrypted JSON: vehicle, vehicle_number, driver_name, driver_phone, route, stay, notes.
            $table->text('checkpoint')->nullable()->after('description');
        });

        Schema::table('users', function (Blueprint $table) {
            // Optional encrypted copy of the traveller's own details, to fill the next trip in one tap.
            $table->text('travel_profile')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('travel_profile');
        });

        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn('checkpoint');
        });

        Schema::dropIfExists('traveller_details');
    }
};
