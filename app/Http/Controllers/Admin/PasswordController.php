<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class PasswordController extends Controller
{
    public function edit(): View
    {
        return view('admin.password.edit');
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', Password::min(8), 'confirmed', 'different:current_password'],
        ], [
            'current_password.current_password' => 'That’s not your current password.',
            'password.different' => 'Pick a password that’s different from your current one.',
        ]);

        $request->user()->update(['password' => $validated['password']]);

        $request->session()->migrate(true);

        return redirect()->route($request->routeIs('purchaser.*') ? 'purchaser.password.edit' : 'admin.password.edit')
            ->with('status', 'Your password has been changed.');
    }
}
