<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\TripJoinController;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Services\ContactVerification;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(RegisterRequest $request, ContactVerification $verification): RedirectResponse
    {
        $data = $request->validated();

        // The very first account on a fresh install owns the admin console, so no
        // credentials ever need to be seeded anywhere.
        $isFirstAccount = User::query()->count() === 0;

        $user = new User([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['contact_type'] === 'email' ? $data['email'] : null,
            'phone' => $data['contact_type'] === 'phone' ? $data['phone'] : null,
            'password' => $data['password'],
            'timezone' => config('app.timezone'),
            'last_seen_at' => now(),
        ]);
        $user->forceFill(['role' => $isFirstAccount ? 'admin' : 'user', 'is_active' => true, 'locale' => app()->getLocale()])->save();

        Auth::login($user);
        $request->session()->regenerate();

        // The codes go out now; a mail or SMS hiccup is shown on the
        // next page, where they can simply ask again.
        $problems = $verification->sendPending($user);

        // The admin account runs the console and nothing else, so it skips the
        // trip invites a traveller account would pick up.
        if ($isFirstAccount) {
            $request->session()->forget(TripJoinController::SESSION_KEY);

            return redirect()->route('admin.dashboard')->with('status', __('Welcome to Sofor, :user! As the first account on this install, this is the administrator account for the console. Register a separate account to plan your own trips.', ['user' => $user->name]));
        }

        // Trip invites for this email or phone, and an invite link they came
        // in through, are picked up once it is verified (finishVerification).
        return redirect()->route('verification.notice')->with(...($problems !== []
            ? ['error', $problems[0]]
            : ['status', $user->email !== null
                ? __('Welcome to Sofor, :user! One last step: we emailed a 6 digit code to :email.', ['user' => $user->name, 'email' => $user->email])
                : __('Welcome to Sofor, :user! One last step: we texted a 6 digit code to :phone.', ['user' => $user->name, 'phone' => Phone::pretty($user->phone)])]));
    }
}
