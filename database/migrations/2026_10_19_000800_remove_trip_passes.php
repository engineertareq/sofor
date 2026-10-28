<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sofor is free: no trip limit, no trip pass. The pass table and the two
     * columns that counted trips against the free allowance go.
     *
     * Payment records are never thrown away, though: an install where somebody
     * actually bought a pass keeps the table (and those rows) as its record.
     */
    public function up(): void
    {
        $paid = Schema::hasTable('trip_passes')
            && DB::table('trip_passes')->where('status', 'completed')->exists();

        if ($paid) {
            return;
        }

        Schema::dropIfExists('trip_passes');

        foreach (['trips_created', 'trip_pass_expires_at'] as $column) {
            if (Schema::hasColumn('users', $column)) {
                Schema::table('users', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }

    /** The structure comes back (empty), so the earlier migration can roll back cleanly. */
    public function down(): void
    {
        if (! Schema::hasTable('trip_passes')) {
            Schema::create('trip_passes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
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
        }

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'trips_created')) {
                $table->unsignedInteger('trips_created')->default(0)->after('role');
            }

            if (! Schema::hasColumn('users', 'trip_pass_expires_at')) {
                $table->timestamp('trip_pass_expires_at')->nullable()->after('trips_created');
            }
        });
    }
};
