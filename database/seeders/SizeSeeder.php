<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Size;

class SizeSeeder extends Seeder
{
    public function run(): void
    {
        $sizes = [
            ['name' => 'XS', 'slug' => 'xs'],
            ['name' => 'S', 'slug' => 's'],
            ['name' => 'M', 'slug' => 'm'],
            ['name' => 'L', 'slug' => 'l'],
            ['name' => 'XL', 'slug' => 'xl'],
            ['name' => 'XXL', 'slug' => 'xxl'],
            ['name' => 'XXXL', 'slug' => 'xxxl'],
        ];

        foreach ($sizes as $size) {
            Size::updateOrCreate(['slug' => $size['slug']], $size);
        }
    }
}
