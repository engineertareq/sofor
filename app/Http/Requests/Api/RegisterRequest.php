<?php

namespace App\Http\Requests\Api;

use App\Http\Requests\Auth\RegisterRequest as WebRegisterRequest;
use App\Support\Username;

/**
 * Sign up from the app: the website's rules. The app's form has no username
 * field, so one is made from the name; it can be changed from the profile.
 */
class RegisterRequest extends WebRegisterRequest
{
    protected function prepareForValidation(): void
    {
        if (blank($this->input('username'))) {
            $this->merge(['username' => Username::suggest((string) $this->input('name'), $this->input('email'))]);
        }

        parent::prepareForValidation();
    }
}
