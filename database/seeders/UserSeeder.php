<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name' => 'Admin User',
                'email' => 'admin@bilihub.com',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'phone' => '09123456789',
                'address' => '123 Admin Street, Manila',
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Buyer User',
                'email' => 'buyer@bilihub.com',
                'password' => Hash::make('password'),
                'role' => 'customer',
                'phone' => '09234567890',
                'address' => '456 Buyer Avenue, Quezon City',
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Seller User',
                'email' => 'seller@bilihub.com',
                'password' => Hash::make('password'),
                'role' => 'seller',
                'phone' => '09345678901',
                'address' => '789 Seller Road, Makati',
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Rider User',
                'email' => 'rider@bilihub.com',
                'password' => Hash::make('password'),
                'role' => 'rider',
                'phone' => '09456789012',
                'address' => '321 Rider Lane, Pasig',
                'email_verified_at' => now(),
            ],

            [
                'name' => 'Logistics Owner',
                'email' => 'logistic@bilihub.com',
                'password' => Hash::make('password'),
                'role' => 'logistic_owner',
                'phone' => '09678901234',
                'address' => '789 Logistics Ave, Pasig',
                'email_verified_at' => now(),
            ],
        ];

        foreach ($users as $userData) {
            User::firstOrCreate(
                ['email' => $userData['email']],
                $userData
            );
        }
    }
}
