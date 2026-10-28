<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // An advance the trip collects before or during the trip: so much per
        // person, paid to one collector, who then pays shared costs from it.
        Schema::create('trip_collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('collector_member_id')->constrained('trip_members')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 80);
            $table->decimal('amount_per_person', 12, 2);
            $table->date('due_on')->nullable();
            $table->string('note', 240)->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::table('settlements', function (Blueprint $table) {
            $table->foreignId('collection_id')->nullable()->after('trip_id')->constrained('trip_collections')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('settlements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('collection_id');
        });

        Schema::dropIfExists('trip_collections');
    }
};
