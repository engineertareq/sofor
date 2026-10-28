<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Budgets can be set per person (the group total then grows with the trip),
 * and the group is told once when spending passes 80% and again at 100%.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->decimal('budget_per_person', 12, 2)->nullable()->after('budget');
            // The last alert sent: 0 (none yet), 80 or 100.
            $table->unsignedTinyInteger('budget_alert_level')->default(0)->after('budget_per_person');
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn(['budget_per_person', 'budget_alert_level']);
        });
    }
};
