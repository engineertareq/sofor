<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Verification arrives with this release. Accounts made before it could not
     * verify anything, so their contacts are treated as verified rather than
     * locking everybody out of the trips they already have. New accounts and
     * changed contacts verify as normal.
     */
    public function up(): void
    {
        DB::table('users')
            ->whereNotNull('email')
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => DB::raw('COALESCE(created_at, CURRENT_TIMESTAMP)')]);

        DB::table('users')
            ->whereNotNull('phone')
            ->whereNull('phone_verified_at')
            ->update(['phone_verified_at' => DB::raw('COALESCE(created_at, CURRENT_TIMESTAMP)')]);
    }

    /** Nothing to undo: which contacts were verified before cannot be told apart afterwards. */
    public function down(): void {}
};
