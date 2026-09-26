<?php

return [

    'uses_sizes' => [
        'womens-apparel',
        'mens-apparel',
        'kids-and-baby',
        'sports-and-outdoors',
    ],

    'subcategories' => [
        'pet-supplies' => [
            ['name' => 'Pet Food', 'slug' => 'pet-food'],
            ['name' => 'Accessories', 'slug' => 'pet-accessories'],
            ['name' => 'Toys & Care', 'slug' => 'pet-toys-care'],
        ],
        'kids-and-baby' => [
            ['name' => 'Baby Clothing', 'slug' => 'baby-clothing'],
            ['name' => 'Toys & Games', 'slug' => 'kids-toys'],
            ['name' => 'Baby Care', 'slug' => 'baby-care'],
            ['name' => 'Feeding', 'slug' => 'feeding'],
        ],
        'electronics-and-gadgets' => [
            ['name' => 'Smartphones', 'slug' => 'smartphones'],
            ['name' => 'Computers & Tablets', 'slug' => 'computers-tablets'],
            ['name' => 'Audio', 'slug' => 'audio'],
            ['name' => 'Wearables', 'slug' => 'wearables'],
        ],
        'home-and-garden' => [
            ['name' => 'Furniture', 'slug' => 'furniture'],
            ['name' => 'Decor', 'slug' => 'home-decor'],
            ['name' => 'Kitchen', 'slug' => 'kitchen'],
            ['name' => 'Garden', 'slug' => 'garden'],
        ],
        'womens-apparel' => [
            ['name' => 'Clothing', 'slug' => 'womens-clothing'],
            ['name' => 'Shoes', 'slug' => 'womens-shoes'],
            ['name' => 'Bags & Accessories', 'slug' => 'womens-bags-accessories'],
        ],
        'sports-and-outdoors' => [
            ['name' => 'Exercise', 'slug' => 'exercise'],
            ['name' => 'Outdoor Gear', 'slug' => 'outdoor-gear'],
            ['name' => 'Bicycles', 'slug' => 'bicycles'],
            ['name' => 'Team Sports', 'slug' => 'team-sports'],
        ],
        'mens-apparel' => [
            ['name' => 'Clothing', 'slug' => 'mens-clothing'],
            ['name' => 'Shoes', 'slug' => 'mens-shoes'],
            ['name' => 'Bags & Accessories', 'slug' => 'mens-bags-accessories'],
        ],
        'health-and-beauty' => [
            ['name' => 'Skincare', 'slug' => 'skincare'],
            ['name' => 'Hair Care', 'slug' => 'hair-care'],
            ['name' => 'Fragrance', 'slug' => 'fragrance'],
            ['name' => 'Health Supplements', 'slug' => 'health-supplements'],
            ['name' => 'Makeup', 'slug' => 'makeup'],
        ],
    ],

    'product_attributes' => [
        'pet-supplies' => [
            ['key' => 'brand', 'label' => 'Brand', 'type' => 'text'],
            ['key' => 'material', 'label' => 'Material', 'type' => 'text'],
        ],
        'kids-and-baby' => [
            ['key' => 'material', 'label' => 'Material', 'type' => 'text'],
            ['key' => 'age_group', 'label' => 'Age Group', 'type' => 'text'],
        ],
        'electronics-and-gadgets' => [
            ['key' => 'brand', 'label' => 'Brand', 'type' => 'text'],
            ['key' => 'model', 'label' => 'Model', 'type' => 'text'],
            ['key' => 'warranty', 'label' => 'Warranty', 'type' => 'select', 'options' => ['6 months', '1 year', '2 years', 'No warranty']],
            ['key' => 'specifications', 'label' => 'Specifications', 'type' => 'textarea'],
        ],
        'home-and-garden' => [
            ['key' => 'material', 'label' => 'Material', 'type' => 'text'],
            ['key' => 'dimensions', 'label' => 'Dimensions', 'type' => 'text'],
            ['key' => 'color', 'label' => 'Color', 'type' => 'text'],
        ],
        'womens-apparel' => [
            ['key' => 'material', 'label' => 'Material', 'type' => 'text'],
            ['key' => 'gender', 'label' => 'Gender', 'type' => 'select', 'options' => ['Women', 'Unisex']],
        ],
        'sports-and-outdoors' => [
            ['key' => 'brand', 'label' => 'Brand', 'type' => 'text'],
            ['key' => 'material', 'label' => 'Material', 'type' => 'text'],
        ],
        'mens-apparel' => [
            ['key' => 'material', 'label' => 'Material', 'type' => 'text'],
            ['key' => 'gender', 'label' => 'Gender', 'type' => 'select', 'options' => ['Men', 'Unisex']],
        ],
        'health-and-beauty' => [
            ['key' => 'brand', 'label' => 'Brand', 'type' => 'text'],
            ['key' => 'variant', 'label' => 'Variant', 'type' => 'text'],
            ['key' => 'volume_weight', 'label' => 'Volume / Weight', 'type' => 'text'],
        ],
    ],

    'variation_attributes' => [
        'pet-supplies' => [
            ['key' => 'color', 'label' => 'Color', 'type' => 'text'],
        ],
        'kids-and-baby' => [
            ['key' => 'color', 'label' => 'Color', 'type' => 'color'],
            ['key' => 'size', 'label' => 'Size', 'type' => 'size'],
        ],
        'electronics-and-gadgets' => [
            ['key' => 'color', 'label' => 'Color', 'type' => 'text'],
            ['key' => 'model', 'label' => 'Model', 'type' => 'text'],
        ],
        'home-and-garden' => [
            ['key' => 'color', 'label' => 'Color', 'type' => 'color'],
            ['key' => 'material', 'label' => 'Material', 'type' => 'text'],
        ],
        'womens-apparel' => [
            ['key' => 'color', 'label' => 'Color', 'type' => 'color'],
            ['key' => 'size', 'label' => 'Size', 'type' => 'size'],
        ],
        'sports-and-outdoors' => [
            ['key' => 'color', 'label' => 'Color', 'type' => 'color'],
            ['key' => 'size', 'label' => 'Size', 'type' => 'size'],
        ],
        'mens-apparel' => [
            ['key' => 'color', 'label' => 'Color', 'type' => 'color'],
            ['key' => 'size', 'label' => 'Size', 'type' => 'size'],
        ],
        'health-and-beauty' => [
            ['key' => 'variant', 'label' => 'Variant', 'type' => 'text'],
        ],
    ],

    'color_options' => [
        'Black', 'White', 'Red', 'Blue', 'Green', 'Yellow', 'Pink', 'Purple',
        'Orange', 'Brown', 'Grey', 'Beige', 'Navy', 'Maroon', 'Teal',
    ],

];
