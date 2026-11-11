<?php

namespace App\Http\Requests\Api;

use App\Http\Requests\RecordSettlementRequest as WebRecordSettlementRequest;
use App\Support\Poisha;

/** A payment from the app: the website's rules, with the amount in poisha and a retry-safe id. */
class RecordSettlementRequest extends WebRecordSettlementRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['amount' => Poisha::toTaka($this->input('amount'))]);

        parent::prepareForValidation();
    }

    public function rules(): array
    {
        return parent::rules() + ['client_uuid' => ['nullable', 'uuid']];
    }
}
