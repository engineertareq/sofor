<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Username;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Live "is this username free?" for the sign up and profile forms. */
class UsernameController extends Controller
{
    public function check(Request $request): JsonResponse
    {
        $username = Username::normalize($request->string('username')->toString());
        $current = $request->user()?->username;

        [$available, $message] = match (true) {
            $username === '' => [false, __('Pick a username for your public profile.')],
            $username === $current => [true, __('That is your username now.')],
            preg_match(Username::PATTERN, $username) !== 1 => [false, __('Use 3 to 30 lowercase letters, numbers or underscores, with at least one letter.')],
            Username::isReserved($username) => [false, __('That username is reserved. Try another one.')],
            User::query()->where('username', $username)->exists() => [false, __('That username is taken. Try another one.')],
            default => [true, __('That username is free.')],
        };

        return response()->json([
            'username' => $username,
            'available' => $available,
            'message' => $message,
        ]);
    }
}
