<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Codes texted to a phone: to verify the number, or to reset a password.
        // Only a keyed hash of each code is kept.
        Schema::create('verification_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('purpose', 30);
            $table->string('destination', 32);
            $table->string('code_hash', 64);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index(['purpose', 'destination', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_codes');
    }
};
