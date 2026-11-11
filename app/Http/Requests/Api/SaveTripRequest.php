<?php

namespace App\Http\Requests\Api;

use App\Support\Poisha;

/**
 * The budget on a trip arrives from the app as poisha (`budget` with
 * `budget_mode` group or person); the website's trip rules expect taka.
 */
trait SaveTripRequest
{
    protected function prepareForValidation(): void
    {
        $budget = Poisha::toTaka($this->input('budget'));
        $perPerson = $this->input('budget_mode') === 'person';

        $this->merge([
            'budget' => $perPerson ? null : $budget,
            'budget_per_person' => $perPerson ? $budget : null,
        ]);

        parent::prepareForValidation();
    }
}
