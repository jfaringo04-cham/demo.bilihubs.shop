<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['name' => 'Admin User', 'email' => 'admin@bilihub.com', 'password' => Hash::make('password'), 'role_name' => 'admin'],
            ['name' => 'Buyer User', 'email' => 'buyer@bilihub.com', 'password' => Hash::make('password'), 'role_name' => 'buyer'],
            ['name' => 'Seller User', 'email' => 'seller@bilihub.com', 'password' => Hash::make('password'), 'role_name' => 'seller'],
            ['name' => 'Rider User', 'email' => 'rider@bilihub.com', 'password' => Hash::make('password'), 'role_name' => 'rider'],
            ['name' => 'Logistics Owner', 'email' => 'logistic@bilihub.com', 'password' => Hash::make('password'), 'role_name' => 'logistics'],
        ];

        foreach ($users as $userData) {
            $roleName = $userData['role_name'];
            unset($userData['role_name']);

            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                $userData
            );

            $role = Role::where('name', $roleName)->firstOrFail();
            $user->roles()->syncWithoutDetaching([$role->id]);
        }
    }
}