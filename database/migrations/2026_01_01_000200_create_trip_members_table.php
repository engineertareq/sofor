<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('role', 16)->default('member');
            $table->string('status', 16)->default('active')->index();
            $table->unsignedInteger('share_units')->default(1);
            $table->boolean('include_in_split')->default(true);
            $table->string('invite_token', 40)->nullable()->unique();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['trip_id', 'user_id']);
            $table->index(['trip_id', 'status']);
            $table->index(['email']);
            $table->index(['phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_members');
    }
};
