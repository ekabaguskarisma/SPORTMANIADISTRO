<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Akun Admin Operasional
        User::create([
            'name' => 'Admin Sport Mania',
            'email' => 'admin@sportmania.com',
            'password' => Hash::make('Admin123'),
            'role' => 'admin',
        ]);

        // Akun Pelanggan Uji Coba
        User::create([
            'name' => 'Pelanggan Setia',
            'email' => 'user@gmail.com',
            'password' => Hash  ::make('User123'),
            'role' => 'user',
        ]);
    }
}