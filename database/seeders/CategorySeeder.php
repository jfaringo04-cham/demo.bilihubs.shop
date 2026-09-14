<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Pet Supplies', 'slug' => 'pet-supplies', 'description' => 'Food, accessories, and care for pets'],
            ['name' => 'Kids and Baby', 'slug' => 'kids-and-baby', 'description' => 'Products for babies and children'],
            ['name' => 'Electronics and Gadgets', 'slug' => 'electronics-and-gadgets', 'description' => 'Smartphones, tablets, and gadgets'],
            ['name' => 'Home and Garden', 'slug' => 'home-and-garden', 'description' => 'Home decor, furniture, and garden supplies'],
            ['name' => "Women's Apparel", 'slug' => "womens-apparel", 'description' => "Clothing and fashion for women"],
            ['name' => 'Sports and Outdoors', 'slug' => 'sports-and-outdoors', 'description' => 'Sports gear and outdoor equipment'],
            ['name' => "Men's Apparel", 'slug' => "mens-apparel", 'description' => 'Clothing and fashion for men'],
            ['name' => 'Health and Beauty', 'slug' => 'health-and-beauty', 'description' => 'Health, beauty, and personal care products'],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(['slug' => $category['slug']], $category);
        }
    }
}
