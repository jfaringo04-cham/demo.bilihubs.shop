<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Seller;
use Illuminate\Database\Seeder;

class MigrateProductSellersSeeder extends Seeder
{
    public function run(): void
    {
        // Kunin ang products na may existing user_id
        Product::whereNotNull('user_id')
            ->get()
            ->each(function (Product $product) {

                // Hanapin ang seller record na pagmamay-ari
                // ng existing product user_id
                $seller = Seller::where('user_id', $product->user_id)->first();

                if ($seller) {
                    $product->seller_id = $seller->id;
                    $product->save();
                }
            });
    }
}