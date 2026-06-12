<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LocaleController extends Controller
{
    /**
     * Switch the user's interface language. The locale must be one of the
     * active languages configured in the admin settings panel.
     */
    public function update(Request $request): RedirectResponse
    {
        $available = array_keys(config('app.languages', []));

        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in($available)],
        ]);

        // Persist the locale in a long-lived cookie rather than the session:
        // the session is flushed on logout, which would silently reset the
        // interface language back to the admin default after sign-out. A
        // forever cookie survives logout AND refresh — mirroring how the theme
        // preference persists — so the chosen language sticks. SetLocale reads
        // this cookie (falling back to the legacy session key) on every request.
        return back()->withCookie(cookie()->forever('locale', $validated['locale']));
    }
}
