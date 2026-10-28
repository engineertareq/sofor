<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_members', function (Blueprint $table) {
            // A short personal link (/p/<token>) showing what this traveller
            // owes and where to send it - no account needed.
            $table->string('pay_token', 16)->nullable()->unique()->after('invite_token');
        });

        Schema::table('trips', function (Blueprint $table) {
            // Also text the automatic reminders, paid from the leader's SMS wallet.
            $table->boolean('sms_reminders')->default(false)->after('budget');
        });

        // Every reminder sent, so nobody is reminded twice in a day and the
        // automatic ones stop after a few.
        Schema::create('payment_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trip_member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('channels', 40);
            $table->boolean('automatic')->default(false);
            $table->timestamps();

            $table->index(['trip_member_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_reminders');

        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn('sms_reminders');
        });

        Schema::table('trip_members', function (Blueprint $table) {
            $table->dropUnique(['pay_token']);
            $table->dropColumn('pay_token');
        });
    }
};
