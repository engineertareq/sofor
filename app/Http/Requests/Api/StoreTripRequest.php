<?php

namespace App\Http\Requests\Api;

use App\Http\Requests\StoreTripRequest as WebStoreTripRequest;

/** A new trip from the app: the website's rules, with the budget in poisha. */
class StoreTripRequest extends WebStoreTripRequest
{
    use SaveTripRequest;
}
