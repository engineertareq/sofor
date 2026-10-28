<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use App\Support\Seo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** English or Bangla: remembered for the visit, and on the account when signed in. */
class LocaleController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $locale = $request->validate([
            'locale' => ['required', Rule::in(array_keys(SetLocale::LOCALES))],
        ])['locale'];

        $request->session()->put('locale', $locale);
        $request->user()?->forceFill(['locale' => $locale])->save();

        // Back to the same page, without a ?lang= that would switch it straight back.
        return redirect()->to(Seo::withoutLang(url()->previous()));
    }
}
