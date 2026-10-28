<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per area a traveller has been to, on any travel map.
        Schema::create('visited_places', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // A key of config/travel_maps.php, e.g. bd or world.
            $table->string('map', 20);
            // The ISO code inside that map, e.g. BD-13 (Dhaka) or TH.
            $table->string('area', 12);
            // Set when the visit was picked up from one of the traveller's trips.
            $table->foreignId('trip_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'map', 'area']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visited_places');
    }
};
