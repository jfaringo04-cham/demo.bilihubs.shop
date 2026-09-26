<?php

namespace Database\Seeders;

use App\Models\Seller;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MigrateSellersSeeder extends Seeder
{
    public function run(): void
    {
        // Kunin lahat ng users na may seller role
        $sellerUsers = User::whereHas('roles', function ($query) {
            $query->where('name', 'seller');
        })->get();

        foreach ($sellerUsers as $user) {

            // Gamitin ang existing store/business name
            // Kapag walang store_name at business_name,
            // gagamitin ang user's name + "Shop"
            $shopName = $user->store_name
                ?: $user->business_name
                ?: $user->name . ' Shop';

            // Gumawa ng unique slug
            $baseSlug = Str::slug($shopName);
            $slug = $baseSlug;
            $counter = 1;

            while (
                Seller::where('slug', $slug)
                    ->where('user_id', '!=', $user->id)
                    ->exists()
            ) {
                $slug = $baseSlug . '-' . $counter;
                $counter++;
            }

            // Gumawa o mag-update ng seller/shop record
            Seller::updateOrCreate(
                [
                    'user_id' => $user->id,
                ],
                [
                    'name' => $shopName,
                    'slug' => $slug,
                    'description' => null,
                    'logo_path' => $user->logo,
                    'banner_path' => null,

                    // I-convert ang old user status
                    // papunta sa bagong seller approval status
                    'status' => match ($user->status) {
                        'active' => 'approved',
                        'approved' => 'approved',
                        'rejected' => 'rejected',
                        'pending' => 'pending',
                        default => 'pending',
                    },

                    'rejection_reason' => $user->rejection_reason,
                    'commission_bps' => 0,
                    'pickup_address_id' => null,
                ]
            );
        }
    }
}