<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('donations', function (Blueprint $table) {
            $table->id();

            // Who gave it. Both stay optional so somebody can support the project
            // without leaving any personal detail behind.
            $table->string('name', 80)->nullable();
            $table->string('contact', 160)->nullable();
            $table->string('message', 240)->nullable();

            // Money: `amount` is the gift, `total` is what the donor's bKash was
            // billed. bKash's own MFS fee is recorded in `charge` and absorbed by
            // Sofor, so it is never added on top of what the donor pays.
            $table->decimal('amount', 12, 2);
            $table->decimal('charge', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->decimal('percent', 5, 2)->default(1.5);

            $table->string('invoice', 64)->unique();
            $table->string('gateway', 32)->default('bkash');
            $table->string('payment_id', 64)->nullable()->index();
            $table->string('trx_id', 64)->nullable()->unique();
            $table->string('status', 20)->default('pending')->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('donations');
    }
};
