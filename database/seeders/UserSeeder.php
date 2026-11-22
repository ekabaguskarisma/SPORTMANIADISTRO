<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Akun Admin Operasional
        User::updateOrCreate(
            ['email' => 'admin@sportmania.com'],
            [
                'name' => 'Admin Sport Mania',
                'password' => Hash::make('Admin123'),
                'role' => 'admin',
            ]
        );

        // Akun Pelanggan Uji Coba
        User::updateOrCreate(
            ['email' => 'user@gmail.com'],
            [
                'name' => 'Pelanggan Setia',
                'password' => Hash::make('User123'),
                'role' => 'user',
            ]
        );
    }
}