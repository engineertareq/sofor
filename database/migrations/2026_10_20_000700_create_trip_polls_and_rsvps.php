<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deciding together: quick polls inside a trip ("Friday or Saturday?",
 * "Bus or train?") and every traveller's answer to "are you coming?".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_polls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('question', 140);
            $table->boolean('multiple')->default(false);
            $table->timestamp('closes_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('trip_poll_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poll_id')->constrained('trip_polls')->cascadeOnDelete();
            $table->string('label', 80);
            $table->unsignedTinyInteger('position')->default(0);
        });

        Schema::create('trip_poll_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poll_id')->constrained('trip_polls')->cascadeOnDelete();
            $table->foreignId('option_id')->constrained('trip_poll_options')->cascadeOnDelete();
            $table->foreignId('trip_member_id')->constrained('trip_members')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['option_id', 'trip_member_id']);
            $table->index(['poll_id', 'trip_member_id']);
        });

        Schema::table('trip_members', function (Blueprint $table) {
            // going, maybe or not_going; null until they answer.
            $table->string('rsvp', 12)->nullable()->after('include_in_split');
            $table->timestamp('rsvp_at')->nullable()->after('rsvp');
        });
    }

    public function down(): void
    {
        Schema::table('trip_members', function (Blueprint $table) {
            $table->dropColumn(['rsvp', 'rsvp_at']);
        });

        Schema::dropIfExists('trip_poll_votes');
        Schema::dropIfExists('trip_poll_options');
        Schema::dropIfExists('trip_polls');
    }
};
