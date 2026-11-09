<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\SQLiteTestCase;

class StorefrontCategoryFilteringTest extends SQLiteTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (
            ! app()->environment('testing') ||
            config('database.default') !== 'sqlite' ||
            config('database.connections.sqlite.database') !== ':memory:'
        ) {
            throw new \RuntimeException('Storefront tests require the isolated in-memory SQLite database.');
        }

        $this->withoutVite();
        $this->createSchema();
    }

    private function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->json('selling_categories')->nullable();
            $table->string('business_name')->nullable();
            $table->string('store_name')->nullable();
            $table->string('logo')->nullable();
            $table->string('barangay')->nullable();
            $table->string('barangay_name')->nullable();
            $table->string('municipality')->nullable();
            $table->string('municipality_name')->nullable();
            $table->string('province')->nullable();
            $table->string('province_name')->nullable();
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('role_id');
            $table->primary(['user_id', 'role_id']);
        });

        Schema::create('sellers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('banner_path')->nullable();
            $table->string('status')->default('approved');
            $table->text('rejection_reason')->nullable();
            $table->unsignedInteger('commission_bps')->default(0);
            $table->unsignedBigInteger('pickup_address_id')->nullable();
            $table->boolean('is_on_vacation')->default(false);
            $table->date('vacation_ends_on')->nullable();
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('price_minor')->default(10000);
            $table->string('image')->nullable();
            $table->string('alt_text')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('subcategory_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('seller_id')->nullable();
            $table->text('description')->nullable();
            $table->integer('stock')->default(10);
            $table->string('image_path')->nullable();
            $table->string('video_path')->nullable();
            $table->string('secondary_image_path')->nullable();
            $table->string('compliance_status')->default('pending');
            $table->text('admin_notes')->nullable();
            $table->text('flagged_reason')->nullable();
            $table->timestamp('flagged_at')->nullable();
            $table->decimal('discount_percent', 5, 2)->nullable();
            $table->unsignedBigInteger('discounted_price_minor')->nullable();
            $table->timestamp('discount_starts_at')->nullable();
            $table->timestamp('discount_ends_at')->nullable();
            $table->string('sku')->nullable();
            $table->integer('low_stock_threshold')->nullable();
            $table->json('attributes')->nullable();
            $table->string('status', 20)->default('published');
            $table->timestamps();
        });

        Schema::create('sizes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->timestamps();
        });

        Schema::create('product_size', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('size_id');
            $table->integer('stock')->default(0);
            $table->primary(['product_id', 'size_id']);
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedTinyInteger('rating')->default(5);
            $table->text('comment')->nullable();
            $table->timestamps();
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->string('path');
            $table->string('alt_text')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    private function createCategory(string $name, string $slug, ?Category $parent = null): Category
    {
        return Category::create([
            'name' => $name,
            'slug' => $slug,
            'parent_id' => $parent?->id,
        ]);
    }

    private function createSeller(?array $categoryIds = []): User
    {
        $role = Role::firstOrCreate(['name' => 'seller']);

        $user = User::create([
            'name' => 'Store Owner ' . (User::count() + 1),
            'email' => 'store' . (User::count() + 1) . '@example.test',
            'password' => 'password',
            'business_name' => 'Test Store ' . (User::count() + 1),
            'selling_categories' => $categoryIds,
        ]);

        $user->roles()->attach($role->id);

        Seller::create([
            'user_id' => $user->id,
            'name' => $user->business_name,
            'slug' => 'test-store-' . $user->id,
            'status' => 'approved',
        ]);

        return $user->fresh();
    }

    private function createProduct(
        User $owner,
        string $name,
        int $categoryId,
        ?int $subcategoryId = null,
        string $complianceStatus = 'approved',
        string $status = 'published'
    ): Product {
        return Product::create([
            'name' => $name,
            'price_minor' => 12500,
            'alt_text' => $name,
            'category_id' => $categoryId,
            'subcategory_id' => $subcategoryId,
            'user_id' => $owner->id,
            'seller_id' => $owner->seller->id,
            'description' => 'Storefront test product',
            'stock' => 10,
            'compliance_status' => $complianceStatus,
            'status' => $status,
        ]);
    }

    public function test_subcategory_filter_includes_new_and_legacy_product_records(): void
    {
        $women = $this->createCategory('Womens Apparel', 'womens-apparel');
        $dresses = $this->createCategory('Dresses', 'dresses', $women);
        $shoes = $this->createCategory('Shoes', 'shoes', $women);
        $seller = $this->createSeller([$women->id]);

        // New structure: parent in category_id, child in subcategory_id.
        $this->createProduct($seller, 'New Structure Dress', $women->id, $dresses->id);

        // Legacy structure: the child category was stored in category_id.
        $this->createProduct($seller, 'Legacy Structure Dress', $dresses->id);

        $this->createProduct($seller, 'Sibling Shoes Product', $women->id, $shoes->id);

        $response = $this->get(route('seller.storefront', [
            'seller' => $seller->id,
            'category' => $women->id,
            'subcategory' => $dresses->id,
        ]));

        $response->assertOk()
            ->assertSee('New Structure Dress')
            ->assertSee('Legacy Structure Dress')
            ->assertDontSee('Sibling Shoes Product');
    }

    public function test_parent_category_filter_includes_new_and_legacy_products_only_from_that_parent(): void
    {
        $women = $this->createCategory('Womens Apparel', 'womens-apparel');
        $dresses = $this->createCategory('Dresses', 'dresses', $women);
        $shoes = $this->createCategory('Shoes', 'shoes', $women);

        $electronics = $this->createCategory('Electronics', 'electronics-and-gadgets');
        $phones = $this->createCategory('Phones', 'phones', $electronics);

        $seller = $this->createSeller([$women->id, $electronics->id]);

        $this->createProduct($seller, 'Parent Stored Dress', $women->id, $dresses->id);
        $this->createProduct($seller, 'Legacy Child Stored Shoes', $shoes->id);
        $this->createProduct($seller, 'Phone From Other Parent', $electronics->id, $phones->id);

        $response = $this->get(route('seller.storefront', [
            'seller' => $seller->id,
            'category' => $women->id,
        ]));

        $response->assertOk()
            ->assertSee('Parent Stored Dress')
            ->assertSee('Legacy Child Stored Shoes')
            ->assertDontSee('Phone From Other Parent');
    }

    public function test_storefront_is_scoped_to_the_requested_seller_and_published_approved_products(): void
    {
        $women = $this->createCategory('Womens Apparel', 'womens-apparel');
        $dresses = $this->createCategory('Dresses', 'dresses', $women);

        $sellerA = $this->createSeller([$women->id]);
        $sellerB = $this->createSeller([$women->id]);

        $this->createProduct($sellerA, 'Seller A Public Product', $women->id, $dresses->id);
        $this->createProduct($sellerB, 'Seller B Private Product', $women->id, $dresses->id);
        $this->createProduct($sellerA, 'Pending Compliance Product', $women->id, $dresses->id, 'pending');
        $this->createProduct($sellerA, 'Draft Product', $women->id, $dresses->id, 'approved', 'draft');

        $response = $this->get(route('seller.storefront', ['seller' => $sellerA->id]));

        $response->assertOk()
            ->assertSee('Seller A Public Product')
            ->assertDontSee('Seller B Private Product')
            ->assertDontSee('Pending Compliance Product')
            ->assertDontSee('Draft Product');
    }

    public function test_legacy_seller_without_registered_categories_gets_categories_from_legacy_products(): void
    {
        $women = $this->createCategory('Womens Apparel', 'womens-apparel');
        $dresses = $this->createCategory('Dresses', 'dresses', $women);
        $seller = $this->createSeller(null);

        $this->createProduct($seller, 'Legacy Only Dress', $dresses->id);

        $response = $this->get(route('seller.storefront', ['seller' => $seller->id]));

        $response->assertOk()
            ->assertSee('Womens Apparel')
            ->assertSee('Legacy Only Dress');
    }

    public function test_non_seller_account_does_not_have_a_public_storefront(): void
    {
        $user = User::create([
            'name' => 'Not A Seller',
            'email' => 'not-seller@example.test',
            'password' => 'password',
        ]);

        $response = $this->get(route('seller.storefront', ['seller' => $user->id]));

        $response->assertNotFound();
    }
}