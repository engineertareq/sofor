<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Codes are emailed now as well as texted, so the address a code went to has
 * to fit an email address, not only a phone number.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verification_codes', function (Blueprint $table) {
            $table->string('destination')->change();
        });
    }

    public function down(): void
    {
        // Email codes would not fit back in; they only live a few minutes anyway.
        DB::table('verification_codes')->where('purpose', 'email_verification')->delete();

        Schema::table('verification_codes', function (Blueprint $table) {
            $table->string('destination', 32)->change();
        });
    }
};
