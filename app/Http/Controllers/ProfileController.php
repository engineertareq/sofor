<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\StreamsMedia;
use App\Http\Requests\ProfileUpdateRequest;
use App\Services\AvatarService;
use App\Services\ContactVerification;
use App\Services\PortfolioService;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfileController extends Controller
{
    use StreamsMedia;

    public function edit(Request $request, PortfolioService $portfolio): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
            'portfolio' => $portfolio->forUser($request->user()),
        ]);
    }

    public function update(ProfileUpdateRequest $request, ContactVerification $verification): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();

        // A new address has to be proven again.
        $emailChanged = $user->email !== ($data['email'] ?? null);
        $phoneChanged = $user->phone !== ($data['phone'] ?? null);

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        if ($phoneChanged) {
            $user->phone_verified_at = null;
        }

        $user->fill([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
        ]);

        if (array_key_exists('profile_public', $data)) {
            $user->profile_public = (bool) $data['profile_public'];
        }

        $user->save();

        if (! $emailChanged && ! $phoneChanged) {
            return back()->with('status', __('Profile updated.'));
        }

        $problems = $verification->sendPending($user);
        $sent = collect([
            $emailChanged && $user->email !== null ? __('a code to :email', ['email' => $user->email]) : null,
            $phoneChanged && $user->phone !== null ? __('a code to :phone', ['phone' => Phone::pretty($user->phone)]) : null,
        ])->filter()->implode(' '.__('and').' ');

        // Nothing new to confirm (a contact was only removed): stay on the profile.
        if (! $user->hasUnverifiedContact()) {
            return back()->with('status', __('Profile updated.'));
        }

        return redirect()->route('verification.notice')->with(...($problems !== []
            ? ['error', __('Profile updated, but :problem', ['problem' => lcfirst($problems[0])])]
            : ['status', __('Profile updated. To confirm the change we sent :sent.', ['sent' => $sent])]));
    }

    public function updateAvatar(Request $request, AvatarService $avatars): RedirectResponse
    {
        $request->validate([
            'avatar' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:'.AvatarService::MAX_KILOBYTES,
                'dimensions:min_width=64,min_height=64',
            ],
            ...$this->cropRules('nullable'),
        ], [
            'avatar.required' => __('Choose a photo to upload.'),
            'avatar.uploaded' => __('That photo could not be uploaded. Try one under 8 MB.'),
            'avatar.image' => __('Use a JPG, PNG or WebP photo.'),
            'avatar.mimes' => __('Use a JPG, PNG or WebP photo.'),
            'avatar.max' => __('Keep the photo under 8 MB.'),
            'avatar.dimensions' => __('That photo is too small. Use one at least 64 pixels across.'),
        ]);

        $avatars->replace($request->user(), $request->file('avatar'), $this->crop($request));

        return back()->with('status', __('Profile photo updated.'));
    }

    /** Move or zoom the square of the photo already uploaded. */
    public function cropAvatar(Request $request, AvatarService $avatars): RedirectResponse
    {
        $request->validate($this->cropRules('required'));

        $avatars->recrop($request->user(), $this->crop($request));

        return back()->with('status', __('Profile photo updated.'));
    }

    /**
     * The whole photo, for the cropper. Only ever its owner's: other people
     * only see the square, through AvatarController. Photos from before the
     * whole one was kept fall back to the square.
     */
    public function avatarOriginal(Request $request): StreamedResponse
    {
        $user = $request->user();
        $path = $user->avatar_original_path ?? $user->avatar_path;
        abort_if($path === null, 404);

        return $this->streamMedia(AvatarService::disk(), $path, [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * The square chosen in the cropper, as fractions of the photo
     * (see AvatarService::normalise()).
     *
     * @return array<string, list<string>>
     */
    private function cropRules(string $presence): array
    {
        return collect(['crop_x', 'crop_y', 'crop_size'])
            ->mapWithKeys(fn (string $field) => [$field => [$presence, 'required_with:crop_x,crop_y,crop_size', 'numeric', 'between:0,1']])
            ->all();
    }

    /** @return array{x: float, y: float, size: float}|null */
    private function crop(Request $request): ?array
    {
        if (! $request->filled('crop_size')) {
            return null;
        }

        return [
            'x' => (float) $request->input('crop_x'),
            'y' => (float) $request->input('crop_y'),
            'size' => (float) $request->input('crop_size'),
        ];
    }

    public function destroyAvatar(Request $request, AvatarService $avatars): RedirectResponse
    {
        $avatars->remove($request->user());

        return back()->with('status', __('Profile photo removed.'));
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $user = $request->user();

        // A new remember token ends "keep me signed in" everywhere else.
        $user->forceFill([
            'password' => Hash::make($data['password']),
            'remember_token' => Str::random(60),
        ])->save();

        // And every other open session: whoever knew the old password is out.
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->where('id', '!=', $request->session()->getId())
                ->delete();
        }

        return back()->with('status', __('Password changed. Your other devices have been signed out.'));
    }
}
