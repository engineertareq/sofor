<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The brands shown in the home page carousel and on /sponsors.
        Schema::create('sponsors', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('website', 255)->nullable();
            $table->string('tier', 20)->default('silver');
            $table->string('tagline', 140)->nullable();
            $table->string('logo_path');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        // "Become a sponsor" requests sent from /sponsors, read in the admin console.
        Schema::create('sponsor_inquiries', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('company', 120);
            $table->string('email', 160);
            $table->string('phone', 30)->nullable();
            $table->string('website', 255)->nullable();
            $table->string('tier', 20)->nullable();
            $table->text('message');
            $table->string('status', 20)->default('new');
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sponsor_inquiries');
        Schema::dropIfExists('sponsors');
    }
};
