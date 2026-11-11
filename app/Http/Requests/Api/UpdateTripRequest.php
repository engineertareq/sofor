<?php

namespace App\Http\Requests\Api;

use App\Http\Requests\UpdateTripRequest as WebUpdateTripRequest;

/** Trip details edited in the app: the website's rules, with the budget in poisha. */
class UpdateTripRequest extends WebUpdateTripRequest
{
    use SaveTripRequest;
}
