<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The administrator's own account page. It shows the same forms as the
 * traveller profile, inside the console, and posts to the same actions.
 */
class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('admin.profile', ['user' => $request->user()]);
    }
}
