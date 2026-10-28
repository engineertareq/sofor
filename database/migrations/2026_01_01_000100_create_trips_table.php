<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('destination')->nullable();
            $table->text('description')->nullable();
            $table->string('cover', 32)->default('aurora');
            $table->string('emoji', 8)->default('🧭');
            $table->char('currency', 3)->default('BDT');
            $table->decimal('budget', 12, 2)->nullable();
            $table->string('status', 16)->default('active')->index();
            $table->string('invite_code', 16)->unique();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trips');
    }
};
