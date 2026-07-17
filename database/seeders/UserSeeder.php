<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'seirenz@toko.test'],
            [
                'name' => 'Seirenz',
                'username' => 'seirenz',
                'password' => Hash::make('123'),
                'role' => User::ROLE_ADMIN,
                'is_active' => true,
            ],
        );

        User::updateOrCreate(
            ['email' => 'admin@toko.test'],
            [
                'name' => 'Admin Toko',
                'password' => Hash::make('password'),
                'role' => User::ROLE_ADMIN,
                'is_active' => true,
            ],
        );

        User::updateOrCreate(
            ['email' => 'packing@toko.test'],
            [
                'name' => 'Packing 1',
                'password' => Hash::make('password'),
                'role' => User::ROLE_PACKING,
                'is_active' => true,
            ],
        );

        User::updateOrCreate(
            ['email' => 'packing2@toko.test'],
            [
                'name' => 'Packing 2',
                'password' => Hash::make('password'),
                'role' => User::ROLE_PACKING,
                'is_active' => true,
            ],
        );
    }
}
