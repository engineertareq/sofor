<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trip_member_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->unsignedInteger('units')->default(1);
            $table->timestamps();

            $table->unique(['expense_id', 'trip_member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_shares');
    }
};
