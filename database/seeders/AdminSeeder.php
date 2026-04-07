<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // Admin account
        User::firstOrCreate(
            ['email' => 'admin@monalisa.com'],
            [
                'name'     => 'Admin',
                'password' => bcrypt('MNL452$$'),
                'role'     => 'admin',
            ]
        );

        // Manager account

        /*
        User::firstOrCreate(
            ['email' => 'manager@monalisa.com'],
            [
                'name'     => 'Manager',
                'password' => bcrypt('password'),
                'role'     => 'manager',
            ]
        );*/
    }
}
