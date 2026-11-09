<?php

namespace Tests\Feature;

use App\Jobs\RunProductComplianceScan;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\ComplianceMonitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ComplianceMonitorTest extends TestCase
{
    use RefreshDatabase;

    private const TEST_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADUlEQVR4nGP4z8AAAAMBAQDJ/pLvAAAAAElFTkSuQmCC';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('s3');
    }

    public function test_legitimate_apparel_terms_in_product_name_alt_text_and_filename_do_not_flag_image(): void
    {
        $path = 'products/womens-underwear-bikini-lingerie.jpg';
        Storage::disk('s3')->put($path, base64_decode(self::TEST_PNG));

        $product = $this->productWithImage($path, [
            'name' => "Women's underwear, bikini and swimsuit set",
            'alt_text' => 'Lingerie and swimwear product photo',
        ]);

        $this->assertNull(ComplianceMonitor::checkImageContent($product));
    }

    public function test_missing_storage_image_is_skipped_instead_of_being_treated_as_a_violation(): void
    {
        $product = $this->productWithImage('products/legacy-image-no-longer-present.jpg');

        $this->assertNull(ComplianceMonitor::checkImageContent($product));
    }

    public function test_exact_administrator_blacklist_match_flags_only_the_product_for_review(): void
    {
        $bytes = base64_decode(self::TEST_PNG);
        $path = 'products/blacklisted-image.png';
        Storage::disk('s3')->put($path, $bytes);

        DB::table('blacklisted_images')->insert([
            'image_hash' => hash('sha256', $bytes),
            'phash' => null,
            'reason' => 'Confirmed prohibited image',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $trigger = ComplianceMonitor::checkImageContent(
            $this->productWithImage($path)
        );

        $this->assertNotNull($trigger);
        $this->assertSame('critical', $trigger['severity']);
        $this->assertTrue($trigger['auto_flag']);
        $this->assertStringContainsString('Confirmed prohibited image', $trigger['message']);
        $this->assertArrayNotHasKey('auto_suspend', $trigger);
    }

    public function test_brand_name_alone_is_not_treated_as_counterfeit_evidence(): void
    {
        $product = new Product([
            'name' => "Nike women's running jacket",
            'description' => 'Authentic branded sportswear in good condition.',
        ]);

        $this->assertNull(ComplianceMonitor::checkIpAndTextMatching($product));
    }

    public function test_explicit_counterfeit_wording_requires_manual_review_without_auto_flagging(): void
    {
        $product = new Product([
            'name' => 'Nike replica-style jacket',
            'description' => 'A replica-inspired fashion item.',
        ]);

        $trigger = ComplianceMonitor::checkIpAndTextMatching($product);

        $this->assertNotNull($trigger);
        $this->assertFalse($trigger['auto_flag']);
        $this->assertTrue($trigger['manual_review']);
    }

    public function test_scan_job_checks_previous_price_history_before_recording_the_new_snapshot(): void
    {
        $admin = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
        ]);
        $adminRole = Role::create(['name' => 'admin']);
        $admin->roles()->attach($adminRole);

        $seller = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
        ]);

        $product = Product::create([
            'name' => 'Price history regression test',
            'description' => 'Original product description',
            'price_minor' => 20000,
            'image' => 'products/not-present.png',
            'alt_text' => 'Product image',
            'user_id' => $seller->id,
            'stock' => 1,
            'compliance_status' => 'approved',
            'status' => 'published',
        ]);

        DB::table('product_price_history')->insert([
            'product_id' => $product->id,
            'price_minor' => 20000,
            'changed_at' => now()->subDay(),
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        DB::table('product_listing_snapshots')->insert([
            'product_id' => $product->id,
            'name' => $product->name,
            'description' => $product->description,
            'snapshot_at' => now()->subDay(),
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        $product->update(['price_minor' => 500]);

        (new RunProductComplianceScan($product->id))->handle();

        $this->assertSame('auto_flagged', $product->fresh()->compliance_status);
        $this->assertDatabaseHas('product_price_history', [
            'product_id' => $product->id,
            'price_minor' => 500,
        ]);
        $this->assertSame(User::STATUS_ACTIVE, $seller->fresh()->status);
    }

    public function test_critical_image_finding_does_not_suspend_the_seller_account(): void
    {
        $admin = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
        ]);
        $adminRole = Role::create(['name' => 'admin']);
        $admin->roles()->attach($adminRole);

        $seller = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
        ]);

        $product = Product::create([
            'name' => 'Blacklisted product image test',
            'price_minor' => 15000,
            'image' => 'products/blacklisted-image.png',
            'alt_text' => 'Product image',
            'user_id' => $seller->id,
            'stock' => 1,
            'compliance_status' => 'approved',
            'status' => 'published',
        ]);

        ComplianceMonitor::notifyAdmins($product, [[
            'bucket' => 'automated_bot',
            'trigger' => 'image_content_violation',
            'message' => 'Exact match to an administrator-blacklisted image.',
            'severity' => 'critical',
            'auto_flag' => true,
            'reasons' => ['Exact blacklist hash match'],
        ]]);

        $this->assertSame(User::STATUS_ACTIVE, $seller->fresh()->status);
        $this->assertNull($seller->fresh()->suspended_at);
        $this->assertSame('auto_flagged', $product->fresh()->compliance_status);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $seller->id,
            'title' => 'Product Temporarily Hidden for Compliance Review',
            'type' => 'product',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $admin->id,
            'type' => 'compliance_alert',
        ]);
    }

    private function productWithImage(string $path, array $attributes = []): Product
    {
        $product = new Product(array_merge([
            'name' => 'Sample product',
            'image' => $path,
            'image_path' => $path,
            'alt_text' => 'Sample product image',
        ], $attributes));

        $product->setRelation('images', collect([
            (object) [
                'path' => $path,
                'alt_text' => $attributes['alt_text'] ?? 'Sample product image',
            ],
        ]));
        $product->setRelation('variants', collect());

        return $product;
    }
}
