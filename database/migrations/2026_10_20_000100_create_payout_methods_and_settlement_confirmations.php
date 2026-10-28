<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Where a traveller wants to be paid: their own bKash, Nagad, Rocket or
        // Upay number, or a bank account. Trip dues go straight there.
        Schema::create('payout_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 16);
            $table->string('account_number', 40);
            $table->string('account_name', 80)->nullable();
            $table->string('bank_name', 80)->nullable();
            $table->string('branch_name', 80)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'provider', 'account_number']);
        });

        // A payment the payer reports waits for the receiver to confirm it.
        Schema::table('settlements', function (Blueprint $table) {
            $table->timestamp('confirmed_at')->nullable()->after('settled_on');
            $table->foreignId('confirmed_by')->nullable()->after('confirmed_at')->constrained('users')->nullOnDelete();
        });

        // Everything recorded before confirmations existed stands as it is.
        DB::table('settlements')->whereNull('confirmed_at')->update(['confirmed_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('settlements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('confirmed_by');
            $table->dropColumn('confirmed_at');
        });

        Schema::dropIfExists('payout_methods');
    }
};
