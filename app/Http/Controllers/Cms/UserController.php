<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Cms/Users/Index', [
            'users' => User::orderBy('name')->get()->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'roleLabel' => $user->roleLabel(),
                'initials' => $user->initials(),
                'active' => $user->is_active,
                'lastLoginAt' => $user->last_login_at?->toIso8601String(),
                'createdAt' => $user->created_at?->toIso8601String(),
            ])->all(),
            'roles' => User::ROLES,
        ]);
    }

    public function store(SaveUserRequest $request): RedirectResponse
    {
        User::create($request->details() + ['password' => $request->password()]);

        return back();
    }

    public function update(SaveUserRequest $request, User $user): RedirectResponse
    {
        $changes = $request->details();
        $password = $request->filled('password') ? $request->password() : null;

        if ($password !== null) {
            $changes['password'] = $password;
        }

        $this->guardLastSuperAdmin($user, $changes);

        $user->forceFill($changes)->save();

        /**
         * A new password strands every session holding the old hash, which is the point when
         * an account is compromised. Changing your own would stand down your own session too,
         * so refresh it — via setUser, because the guard caches its own instance of the user
         * and would otherwise still be comparing against the hash we just replaced.
         */
        if ($password !== null && $user->is($request->user())) {
            Auth::setUser($user);
            Auth::logoutOtherDevices($password);
        }

        return back();
    }

    public function destroy(User $user): RedirectResponse
    {
        abort_if($user->is(request()->user()), 422, 'You cannot delete your own account.');

        $this->guardLastSuperAdmin($user, ['role' => User::CLIENT_ADMIN]);

        $user->delete();

        return back();
    }

    /**
     * Losing the last active super administrator would lock the user and settings
     * modules for everyone, with no way back in through the interface.
     */
    private function guardLastSuperAdmin(User $user, array $changes): void
    {
        $losesAccess = ($changes['role'] ?? $user->role) !== User::SUPER_ADMIN
            || ($changes['is_active'] ?? $user->is_active) === false;

        if (! $user->isSuperAdmin() || ! $losesAccess) {
            return;
        }

        $others = User::where('role', User::SUPER_ADMIN)
            ->where('is_active', true)
            ->whereKeyNot($user->getKey())
            ->exists();

        abort_if(! $others, 422, 'There must be at least one active super administrator.');
    }
}
