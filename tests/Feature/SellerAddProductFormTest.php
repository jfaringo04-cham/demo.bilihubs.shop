<?php

namespace Tests\Feature;

use Tests\TestCase;

class SellerAddProductFormTest extends TestCase
{
    private function bladeSource(): string
    {
        return file_get_contents(resource_path('views/seller/products/create.blade.php'));
    }

    public function test_images_section_uses_the_images_array_input(): void
    {
        $blade = $this->bladeSource();

        $this->assertStringContainsString('name="images[]"', $blade);
        $this->assertStringContainsString('id="images"', $blade);
        $this->assertStringContainsString('accept="image/jpeg,image/png,image/webp"', $blade);
        $this->assertStringContainsString('multiple', $blade);
    }

    public function test_image_gallery_has_main_preview_thumbnails_and_add_card(): void
    {
        $blade = $this->bladeSource();

        $this->assertStringContainsString('id="imageGallery"', $blade);
        $this->assertStringContainsString('id="imageGalleryMainImage"', $blade);
        $this->assertStringContainsString('id="imageGalleryThumbs"', $blade);
        $this->assertStringContainsString('id="imageGalleryAdd"', $blade);
        $this->assertStringContainsString('Add Image', $blade);

        $this->assertStringContainsString('Upload high-quality images (JPG, PNG, WebP)', $blade);
        $this->assertStringContainsString('Recommended size: 1000 x 1000px', $blade);
    }

    public function test_variant_rows_keep_the_existing_laravel_field_names(): void
    {
        $blade = $this->bladeSource();

        foreach (['name', 'color', 'size', 'price', 'stock', 'sku', 'image'] as $field) {
            $this->assertStringContainsString(
                'name="variations[{{ $variantIndex }}][' . $field . ']"',
                $blade,
                "variant field {$field} must keep its Laravel name"
            );
            $this->assertStringContainsString(
                'name="variations[__INDEX__][' . $field . ']"',
                $blade,
                "template field {$field} must keep its Laravel name"
            );
        }
    }

    public function test_variant_manager_has_toggle_add_and_delete_controls(): void
    {
        $blade = $this->bladeSource();

        $this->assertStringContainsString('id="enable-variants"', $blade);
        $this->assertStringContainsString('id="variantPanel"', $blade);
        $this->assertStringContainsString('id="add-variant-btn"', $blade);
        $this->assertStringContainsString('data-action="variant-remove"', $blade);
        $this->assertStringContainsString('variant-row-template', $blade);
        $this->assertStringContainsString('Create different options for this product such as size, color, or design', $blade);
    }

    public function test_optional_media_and_existing_product_fields_are_preserved(): void
    {
        $blade = $this->bladeSource();

        foreach ([
            'name="category_id"',
            'name="subcategory_id"',
            'name="name"',
            'name="description"',
            'name="price"',
            'name="stock"',
            'name="sku"',
            'name="low_stock_threshold"',
            'name="discount_percent"',
            'name="discount_starts_at"',
            'name="discount_ends_at"',
            'name="secondary_image"',
            'name="video"',
            'name="attributes[shipping][weight]"',
            'name="attributes[shipping][length]"',
            'name="attributes[shipping][width]"',
            'name="attributes[shipping][height]"',
            'name="status"',
        ] as $field) {
            $this->assertStringContainsString($field, $blade, "{$field} must stay on the form");
        }
    }

    public function test_page_has_no_inline_style_or_script_blocks(): void
    {
        $blade = $this->bladeSource();

        $this->assertStringNotContainsString('<style', $blade);
        $this->assertStringNotContainsString('onclick=', $blade);
        $this->assertStringContainsString("@vite('resources/js/seller/products/create.js')", $blade);
        $this->assertStringContainsString("@vite('resources/css/seller/products.css')", $blade);
    }

    public function test_store_validation_accepts_webp_and_caps_images_at_five(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Seller/DashboardController.php'));

        $this->assertStringContainsString("'images' => 'required|array|min:1|max:5'", $source);
        $this->assertStringContainsString("'images.*' => 'image|mimes:jpeg,png,jpg,gif,webp|max:2048'", $source);

        foreach ([
            "'variations' => 'nullable|array'",
            "'variations.*.name' => 'nullable|string|max:255'",
            "'variations.*.color' => 'nullable|string|max:100'",
            "'variations.*.size' => 'nullable|string|max:100'",
            "'variations.*.price' => 'nullable|numeric|min:0|max:999999.99'",
            "'variations.*.stock' => 'nullable|integer|min:0'",
            "'variations.*.sku' => 'nullable|string|max:255'",
            "'variations.*.image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048'",
        ] as $rule) {
            $this->assertStringContainsString($rule, $source);
        }
    }

    public function test_javascript_modules_live_in_the_seller_products_folder(): void
    {
        foreach (['create.js', 'shared.js', 'edit.js'] as $file) {
            $this->assertFileExists(resource_path('js/seller/products/' . $file));
        }

        $shared = file_get_contents(resource_path('js/seller/products/shared.js'));

        $this->assertStringContainsString('export function initImageGallery', $shared);
        $this->assertStringContainsString('export function initVariantManager', $shared);
        $this->assertStringContainsString('export const MAX_IMAGES = 5', $shared);

        foreach ([
            'export function formatFileSize',
            'export function initVariantImageHandling',
            'export function changeVariantImage',
            'export function removeVariantImage',
            'export function removeVariantRow',
            'export function initSizeStockSync',
        ] as $export) {
            $this->assertStringContainsString($export, $shared);
        }
    }

    public function test_variants_stay_in_the_shared_seller_product_stylesheet(): void
    {
        $css = file_get_contents(resource_path('css/seller/products.css'));

        $this->assertStringContainsString('.image-gallery__main', $css);
        $this->assertStringContainsString('.image-gallery__thumb', $css);
        $this->assertStringContainsString('.variant-add-btn', $css);
        $this->assertStringContainsString('.variant-remove', $css);
        $this->assertStringContainsString('.variant-summary', $css);
        $this->assertStringContainsString('.product-form-layout', $css);
    }
}
