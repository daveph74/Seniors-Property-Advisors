<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function edit(): Response
    {
        $user = request()->user();

        return Inertia::render('Cms/Account/Index', [
            'account' => [
                'name' => $user->name,
                'email' => $user->email,
                'roleLabel' => $user->roleLabel(),
                'lastLoginAt' => $user->last_login_at?->toIso8601String(),
            ],
        ]);
    }

    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $password = $request->password();

        $request->user()->forceFill(['password' => $password])->save();

        /**
         * Must run after the save: it verifies the plaintext against the stored hash before
         * re-hashing, and the re-hash is what strands sessions on other devices. This one
         * keeps working because AuthenticateSession refreshes it on the way out.
         */
        Auth::logoutOtherDevices($password);

        return back();
    }
}
