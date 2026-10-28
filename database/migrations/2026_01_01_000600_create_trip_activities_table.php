<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('trip_member_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 40)->index();
            $table->string('description');
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->nullable()->index();

            $table->index(['trip_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_activities');
    }
};
