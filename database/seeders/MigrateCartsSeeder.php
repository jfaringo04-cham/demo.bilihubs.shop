<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MigrateCartsSeeder extends Seeder
{
    public function run(): void
    {
        // Get all users that currently have cart items.
        $userIds = DB::table('cart_items')
            ->whereNotNull('user_id')
            ->distinct()
            ->pluck('user_id');

        foreach ($userIds as $userId) {
            // Create one cart per user without creating duplicates.
            DB::table('carts')->updateOrInsert(
                ['user_id' => $userId],
                [
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}