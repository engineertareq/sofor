<?php

namespace App\Http\Controllers;

use App\Enums\PayoutProvider;
use App\Models\PayoutMethod;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * "How friends pay you": the traveller's own bKash, Nagad, Rocket, Upay or
 * bank account. Trip dues are paid straight into these - Sofor never holds
 * the money.
 */
class PayoutMethodController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $provider = PayoutProvider::tryFrom((string) $request->input('provider'));

        $request->validate([
            'provider' => ['required', Rule::enum(PayoutProvider::class)],
            'account_number' => ['required', 'string', 'max:40'],
            'account_name' => [Rule::requiredIf($provider === PayoutProvider::Bank), 'nullable', 'string', 'max:80'],
            'bank_name' => [Rule::requiredIf($provider === PayoutProvider::Bank), 'nullable', 'string', 'max:80'],
            'branch_name' => ['nullable', 'string', 'max:80'],
        ], [
            'account_name.required' => __('Add the name on the bank account.'),
            'bank_name.required' => __('Which bank is it?'),
        ]);

        $number = $this->normalize($provider, (string) $request->input('account_number'));

        if ($user->payoutMethods()->count() >= PayoutMethod::MAX_PER_USER) {
            throw ValidationException::withMessages(['account_number' => __('You can keep up to :max ways to get paid.', ['max' => PayoutMethod::MAX_PER_USER])]);
        }

        if ($user->payoutMethods()->where('provider', $provider->value)->where('account_number', $number)->exists()) {
            throw ValidationException::withMessages(['account_number' => __('That account is already on your list.')]);
        }

        $method = $user->payoutMethods()->create([
            'provider' => $provider,
            'account_number' => $number,
            'account_name' => $request->input('account_name'),
            'bank_name' => $provider === PayoutProvider::Bank ? $request->input('bank_name') : null,
            'branch_name' => $provider === PayoutProvider::Bank ? $request->input('branch_name') : null,
            // The first one is the main one.
            'is_primary' => ! $user->payoutMethods()->exists(),
        ]);

        return back()->with('status', __(':label :display_number added. Friends will see it when they owe you.', ['label' => $method->provider->label(), 'display_number' => $method->displayNumber()]));
    }

    public function primary(Request $request, PayoutMethod $payoutMethod): RedirectResponse
    {
        abort_unless($payoutMethod->user_id === $request->user()->id, 404);

        $request->user()->payoutMethods()->update(['is_primary' => false]);
        $payoutMethod->update(['is_primary' => true]);

        return back()->with('status', __(':label :display_number is now your main account.', ['label' => $payoutMethod->provider->label(), 'display_number' => $payoutMethod->displayNumber()]));
    }

    public function destroy(Request $request, PayoutMethod $payoutMethod): RedirectResponse
    {
        abort_unless($payoutMethod->user_id === $request->user()->id, 404);

        $wasPrimary = $payoutMethod->is_primary;
        $payoutMethod->delete();

        // Never leave the list without a main account.
        if ($wasPrimary) {
            $request->user()->payoutMethods()->first()?->update(['is_primary' => true]);
        }

        return back()->with('status', __(':label account removed.', ['label' => $payoutMethod->provider->label()]));
    }

    /** @throws ValidationException */
    private function normalize(PayoutProvider $provider, string $number): string
    {
        $digits = preg_replace('/\D+/', '', $number) ?? '';

        return match ($provider) {
            // A Rocket account is the 11 digit mobile number plus one check digit.
            PayoutProvider::Rocket => preg_match('/^01[3-9]\d{9}$/', $digits) === 1
                ? $digits
                : throw ValidationException::withMessages(['account_number' => __('A Rocket account number has 12 digits: your mobile number plus one more digit.')]),
            PayoutProvider::Bank => preg_match('/^[A-Za-z0-9\-]{6,30}$/', trim($number)) === 1
                ? strtoupper(trim($number))
                : throw ValidationException::withMessages(['account_number' => __('That bank account number does not look right.')]),
            default => Phone::isValid($number)
                ? (string) Phone::normalize($number)
                : throw ValidationException::withMessages(['account_number' => __('Enter the :label number, for example 01712345678.', ['label' => $provider->label()])]),
        };
    }
}
