<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function getAll(): \Illuminate\Database\Eloquent\Collection
    {
        return User::orderBy('name')->get(['id', 'name', 'email', 'role', 'is_active', 'created_at']);
    }

    public function updateProfile(User $user, string $name, string $email): void
    {
        $user->update(['name' => $name, 'email' => $email]);
    }

    public function updatePassword(User $user, string $password): void
    {
        $user->update(['password' => Hash::make($password)]);
    }

    public function create(string $name, string $email, string $password, string $role): User
    {
        return User::create([
            'name'     => $name,
            'email'    => $email,
            'password' => Hash::make($password),
            'role'     => $role,
        ]);
    }

    public function toggleStatus(User $user): string
    {
        $user->update(['is_active' => ! $user->is_active]);

        return $user->is_active ? 'activated' : 'deactivated';
    }

    public function delete(User $user): void
    {
        $user->delete();
    }
}
