<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
| Sofor's daily jobs. The server runs `php artisan schedule:run` every minute
| (cron), or `php artisan schedule:work` while developing. Times are Dhaka
| time: the app itself runs in UTC.
*/

// "You still owe" reminders after a trip ends.
Schedule::command('sofor:remind-dues')->timezone('Asia/Dhaka')->dailyAt('10:00')->withoutOverlapping();

// ID details on checkpoint lists are deleted a couple of weeks after each trip.
Schedule::command('sofor:purge-checkpoint-lists')->timezone('Asia/Dhaka')->dailyAt('03:30')->withoutOverlapping();

// A bKash payment that went through but was not booked (a dropped connection,
// a closed tab) is found and credited within the hour.
Schedule::command('bkash:reconcile --days=2')->hourly()->withoutOverlapping();
