<?php

namespace App\Http\Requests\Api;

use App\Http\Requests\StoreExpenseRequest as WebStoreExpenseRequest;
use App\Support\Poisha;

/**
 * A cost from the app. The same rules as the website; only the money arrives
 * as whole poisha and is turned into taka before those rules look at it.
 */
class StoreExpenseRequest extends WebStoreExpenseRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'amount' => Poisha::toTaka($this->input('amount')),
            'custom_shares' => is_array($this->input('custom_shares'))
                ? array_map(fn ($value) => Poisha::toTaka($value), $this->input('custom_shares'))
                : $this->input('custom_shares'),
            'items' => is_array($this->input('items'))
                ? array_map(fn ($item) => is_array($item) ? ['price' => Poisha::toTaka($item['price'] ?? null)] + $item : $item, $this->input('items'))
                : $this->input('items'),
        ]);

        parent::prepareForValidation();
    }
}
