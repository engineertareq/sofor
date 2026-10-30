<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Organised tours: a paid tour an organiser sells to the public (a tour
        // group, a club, an office picnic). Separate from trips, whose costs
        // are split between friends: here everyone pays a fixed price.
        Schema::create('tours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // The public page: /t/<code>
            $table->string('code', 12)->unique();
            $table->string('title', 120);
            $table->string('destination', 120)->nullable();
            $table->text('description')->nullable();
            $table->string('cover', 20)->default('aurora');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('meeting_point', 160)->nullable();
            $table->dateTime('meeting_at')->nullable();
            // Null: no limit.
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->date('closes_on')->nullable();
            $table->string('contact_phone', 20)->nullable();
            $table->string('currency', 3)->default('BDT');
            $table->string('status', 16)->default('draft')->index();
            // Everyone who booked sees what came in and what was spent.
            $table->boolean('show_account')->default(true);
            $table->timestamps();
        });

        // What a seat costs: "Regular", "Couple (2 seats)", "Student".
        Schema::create('tour_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('description', 160)->nullable();
            $table->decimal('price', 12, 2);
            // Seats one booking of it takes, so a couple's package holds two.
            $table->unsignedTinyInteger('seats')->default(1);
            // Paid up front to hold the booking; null asks for the full price.
            $table->decimal('advance', 12, 2)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('tour_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tour_package_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 80);
            $table->string('phone', 20);
            $table->string('email', 160)->nullable();
            $table->unsignedTinyInteger('quantity')->default(1);
            // The price and seats when they booked, so a later price change never moves their total.
            $table->unsignedSmallInteger('seats');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('total', 12, 2);
            $table->string('note', 300)->nullable();
            $table->string('status', 16)->default('booked')->index();
            // Their own page: /r/<token>
            $table->string('token', 40)->unique();
            $table->timestamp('cancelled_at')->nullable();
            // The organiser's last text to them, so reminders cannot be fired off over and over.
            $table->timestamp('reminded_at')->nullable();
            $table->timestamps();

            $table->index(['tour_id', 'phone']);
        });

        // Money from a participant to the organiser, or a refund back. Paid
        // straight to the organiser; Sofor only keeps the record.
        Schema::create('tour_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tour_registration_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10)->default('payment');
            $table->decimal('amount', 12, 2);
            $table->string('method', 16);
            $table->string('reference', 80)->nullable();
            $table->string('sender', 30)->nullable();
            $table->date('paid_on');
            // pending (reported by the participant), confirmed or rejected.
            $table->string('status', 10)->default('confirmed')->index();
            $table->timestamp('confirmed_at')->nullable();
            $table->string('note', 240)->nullable();
            $table->timestamps();
        });

        // What the tour cost the organiser: the bus, the hotel, the guide.
        Schema::create('tour_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_id')->constrained()->cascadeOnDelete();
            $table->string('title', 120);
            $table->decimal('amount', 12, 2);
            $table->string('category', 20);
            $table->date('spent_on');
            $table->string('note', 240)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_expenses');
        Schema::dropIfExists('tour_payments');
        Schema::dropIfExists('tour_registrations');
        Schema::dropIfExists('tour_packages');
        Schema::dropIfExists('tours');
    }
};
