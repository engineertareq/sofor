<?php

namespace App\Http\Controllers;

use App\Models\PayoutMethod;
use App\Services\PayoutMethods;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * "How friends pay you": the traveller's own bKash, Nagad, Rocket, Upay or
 * bank account. Trip dues are paid straight into these - Sofor never holds
 * the money.
 */
class PayoutMethodController extends Controller
{
    public function __construct(private readonly PayoutMethods $methods) {}

    public function store(Request $request): RedirectResponse
    {
        $method = $this->methods->add($request);

        return back()->with('status', __(':label :display_number added. Friends will see it when they owe you.', ['label' => $method->provider->label(), 'display_number' => $method->displayNumber()]));
    }

    public function primary(Request $request, PayoutMethod $payoutMethod): RedirectResponse
    {
        abort_unless($payoutMethod->user_id === $request->user()->id, 404);

        $this->methods->makePrimary($request->user(), $payoutMethod);

        return back()->with('status', __(':label :display_number is now your main account.', ['label' => $payoutMethod->provider->label(), 'display_number' => $payoutMethod->displayNumber()]));
    }

    public function destroy(Request $request, PayoutMethod $payoutMethod): RedirectResponse
    {
        abort_unless($payoutMethod->user_id === $request->user()->id, 404);

        $this->methods->remove($request->user(), $payoutMethod);

        return back()->with('status', __(':label account removed.', ['label' => $payoutMethod->provider->label()]));
    }
}
