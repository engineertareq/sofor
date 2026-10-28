<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Trip passes: the yearly 200 taka unlock that lets an account keep
         * creating trips after its three free ones. Kept as its own table so a
         * payment can be audited (invoice, bKash reference, what was charged)
         * without bloating the users row.
         */
        Schema::create('trip_passes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // `amount` is what Sofor keeps, `total` is what bKash collected once
            // the MFS charge was added on top, exactly like a donation.
            $table->decimal('amount', 12, 2);
            $table->decimal('charge', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->decimal('percent', 5, 2)->default(0);

            $table->unsignedSmallInteger('months')->default(12);
            $table->string('invoice', 64)->unique();
            $table->string('gateway', 32)->default('bkash');
            $table->string('payment_id', 64)->nullable()->index();
            $table->string('trx_id', 64)->nullable()->unique();
            $table->string('status', 20)->default('pending')->index();

            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            // Every trip this account has ever created. Deleting a trip does not
            // hand the slot back, so this is only ever incremented.
            $table->unsignedInteger('trips_created')->default(0)->after('role');

            // Denormalised on purpose: it lets the allowance check run with no
            // extra query, which matters because the app shell asks on every
            // page render. Kept in step with the newest completed pass.
            $table->timestamp('trip_pass_expires_at')->nullable()->after('trips_created');
        });

        // Grandfather existing accounts: whatever they have already made counts
        // towards their lifetime total, so nobody loses anything they had.
        DB::table('users')->update([
            'trips_created' => DB::raw('(select count(*) from trips where trips.user_id = users.id)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['trips_created', 'trip_pass_expires_at']);
        });

        Schema::dropIfExists('trip_passes');
    }
};
