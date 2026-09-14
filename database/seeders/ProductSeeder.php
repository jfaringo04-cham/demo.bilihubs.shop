<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\User;
use App\Models\Category;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $seller = User::where('email', 'seller@bilihub.com')->first();

        if (!$seller) {
            return;
        }

        if ($seller->products()->count() > 0) {
            return;
        }

        $categories = Category::all();

        $productCategoryMap = [
            'mens-apparel' => $categories->firstWhere('slug', 'mens-apparel'),
            'womens-apparel' => $categories->firstWhere('slug', 'womens-apparel'),
            'electronics-and-gadgets' => $categories->firstWhere('slug', 'electronics-and-gadgets'),
            'health-and-beauty' => $categories->firstWhere('slug', 'health-and-beauty'),
            'home-and-garden' => $categories->firstWhere('slug', 'home-and-garden'),
            'kids-and-baby' => $categories->firstWhere('slug', 'kids-and-baby'),
            'pet-supplies' => $categories->firstWhere('slug', 'pet-supplies'),
            'sports-and-outdoors' => $categories->firstWhere('slug', 'sports-and-outdoors'),
        ];

        $products = [
            ['name' => 'Mens Casual T-Shirt', 'price' => 19.99, 'category' => 'mens-apparel', 'stock' => 100, 'description' => 'Comfortable cotton t-shirt for men.'],
            ['name' => 'Womens Summer Dress', 'price' => 49.99, 'category' => 'womens-apparel', 'stock' => 50, 'description' => 'Light and breezy summer dress.'],
            ['name' => 'Smartphone X200', 'price' => 299.99, 'category' => 'electronics-and-gadgets', 'stock' => 30, 'description' => 'Latest smartphone with amazing features.'],
            ['name' => 'Vitamin C Serum', 'price' => 24.99, 'category' => 'health-and-beauty', 'stock' => 80, 'description' => 'Brightening vitamin C serum for skin.'],
            ['name' => 'Phone Case Pro', 'price' => 14.99, 'category' => 'electronics-and-gadgets', 'stock' => 200, 'description' => 'Durable and stylish phone case.'],
            ['name' => 'Luxury Perfume', 'price' => 89.99, 'category' => 'health-and-beauty', 'stock' => 40, 'description' => 'Long-lasting luxury fragrance.'],
            ['name' => '55-inch Smart TV', 'price' => 499.99, 'category' => 'home-and-garden', 'stock' => 15, 'description' => '4K UHD Smart TV with built-in streaming apps.'],
            ['name' => 'Air Fryer', 'price' => 79.99, 'category' => 'home-and-garden', 'stock' => 45, 'description' => 'Healthy cooking air fryer.'],
            ['name' => 'Baby Stroller', 'price' => 129.99, 'category' => 'kids-and-baby', 'stock' => 25, 'description' => 'Lightweight and foldable baby stroller.'],
            ['name' => 'Laptop Pro 15', 'price' => 899.99, 'category' => 'electronics-and-gadgets', 'stock' => 20, 'description' => 'Powerful laptop for work and gaming.'],
            ['name' => 'Modern Sofa', 'price' => 349.99, 'category' => 'home-and-garden', 'stock' => 10, 'description' => 'Comfortable modern sofa for living room.'],
            ['name' => 'DSLR Camera Kit', 'price' => 599.99, 'category' => 'electronics-and-gadgets', 'stock' => 12, 'description' => 'Professional DSLR camera with lens kit.'],
            ['name' => 'Organic Rice 5kg', 'price' => 12.99, 'category' => 'health-and-beauty', 'stock' => 100, 'description' => 'Premium organic rice.'],
            ['name' => 'Travel Backpack', 'price' => 39.99, 'category' => 'sports-and-outdoors', 'stock' => 60, 'description' => 'Durable travel backpack with multiple compartments.'],
            ['name' => 'Board Game Collection', 'price' => 29.99, 'category' => 'kids-and-baby', 'stock' => 35, 'description' => 'Fun board games for family nights.'],
            ['name' => 'Mens Leather Bag', 'price' => 59.99, 'category' => 'mens-apparel', 'stock' => 40, 'description' => 'Genuine leather messenger bag.'],
            ['name' => 'Womens Handbag', 'price' => 69.99, 'category' => 'womens-apparel', 'stock' => 35, 'description' => 'Stylish handbag for everyday use.'],
            ['name' => 'Mens Running Shoes', 'price' => 79.99, 'category' => 'mens-apparel', 'stock' => 55, 'description' => 'Comfortable running shoes for men.'],
            ['name' => 'Gold Earrings', 'price' => 34.99, 'category' => 'health-and-beauty', 'stock' => 70, 'description' => 'Elegant gold-plated earrings.'],
            ['name' => 'Motorcycle Helmet', 'price' => 49.99, 'category' => 'sports-and-outdoors', 'stock' => 30, 'description' => 'Safety-approved motorcycle helmet.'],
            ['name' => 'Womens Heels', 'price' => 54.99, 'category' => 'womens-apparel', 'stock' => 45, 'description' => 'Stylish heels for special occasions.'],
            ['name' => 'Art Stationery Set', 'price' => 19.99, 'category' => 'kids-and-baby', 'stock' => 90, 'description' => 'Complete art and stationery set.'],
            ['name' => 'Dog Food 10kg', 'price' => 29.99, 'category' => 'pet-supplies', 'stock' => 60, 'description' => 'Premium dog food for all breeds.'],
            ['name' => 'Wireless Earbuds', 'price' => 39.99, 'category' => 'electronics-and-gadgets', 'stock' => 80, 'description' => 'True wireless earbuds with noise cancellation.'],
            ['name' => 'Gaming Console', 'price' => 399.99, 'category' => 'electronics-and-gadgets', 'stock' => 18, 'description' => 'Next-gen gaming console with 1TB storage.'],
        ];

        foreach ($products as $index => $productData) {
            $cat = $productCategoryMap[$productData['category']] ?? $categories->first();
            if (!$cat) {
                continue;
            }
            Product::create([
                'name' => $productData['name'],
                'price' => $productData['price'],
                'category_id' => $cat->id,
                'user_id' => $seller->id,
                'stock' => $productData['stock'],
                'description' => $productData['description'],
                'image' => 'products/product' . ($index + 1) . '.jpg',
                'alt_text' => $productData['name'],
                'image_path' => 'products/product' . ($index + 1) . '.jpg',
            ]);
        }
    }
}
