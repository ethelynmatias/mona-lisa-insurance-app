<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function __construct(private UserService $users) {}

    public function index(): Response
    {
        return Inertia::render('Settings', [
            'users' => Auth::user()->isAdmin() ? $this->users->getAll() : [],
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $request->validate([
            'name'  => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
        ]);

        $this->users->updateProfile($user, $request->name, $request->email);

        return back()->with('success', 'Profile updated successfully.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ]);

        $this->users->updatePassword(Auth::user(), $request->password);

        return back()->with('success', 'Password updated successfully.');
    }

    public function createUser(Request $request): RedirectResponse
    {
        $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
            'role'     => ['required', 'in:admin,manager'],
        ]);

        $this->users->create($request->name, $request->email, $request->password, $request->role);

        return back()->with('success', 'User created successfully.');
    }

    public function toggleUserStatus(User $user): RedirectResponse
    {
        if ($user->id === Auth::id()) {
            return back()->with('error', 'You cannot change your own account status.');
        }

        $status = $this->users->toggleStatus($user);

        return back()->with('success', "User {$status} successfully.");
    }

    public function deleteUser(User $user): RedirectResponse
    {
        if ($user->id === Auth::id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        $this->users->delete($user);

        return back()->with('success', 'User deleted successfully.');
    }
}
