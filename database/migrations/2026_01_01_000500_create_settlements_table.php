<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_member_id')->constrained('trip_members')->cascadeOnDelete();
            $table->foreignId('to_member_id')->constrained('trip_members')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('method', 24)->default('bkash');
            $table->string('reference')->nullable();
            $table->string('note')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('settled_on');
            $table->timestamps();

            $table->index(['trip_id', 'settled_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlements');
    }
};
