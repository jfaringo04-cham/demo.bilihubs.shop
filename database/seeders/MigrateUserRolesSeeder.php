<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class MigrateUserRolesSeeder extends Seeder
{
    public function run(): void
    {
        // Legacy migration seeder.
        // User roles are now stored in the role_user pivot table.
        // New users receive their roles through UserSeeder or application logic.
    }
}