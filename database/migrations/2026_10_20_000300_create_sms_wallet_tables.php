<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Prepaid SMS credit. Texts a traveller sends to their group (payment
        // reminders, pay links, check-ins) are paid from it; verification and
        // password codes stay free.
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('sms_balance', 10, 2)->default(0);
        });

        // A top-up paid through Sofor's bKash merchant account.
        Schema::create('sms_topups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('invoice', 40)->unique();
            $table->string('gateway', 16)->default('bkash');
            $table->string('payment_id', 64)->nullable()->index();
            $table->string('trx_id', 64)->nullable()->unique();
            $table->string('status', 16)->default('pending')->index();
            $table->timestamps();
        });

        // Every change to a balance: credits in, texts out, refunds back.
        Schema::create('sms_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16);
            $table->decimal('amount', 10, 2);
            $table->decimal('balance_after', 10, 2);
            $table->unsignedSmallInteger('messages')->default(0);
            $table->string('description', 160);
            $table->string('recipient', 24)->nullable();
            $table->foreignId('trip_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sms_topup_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_transactions');
        Schema::dropIfExists('sms_topups');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('sms_balance');
        });
    }
};
