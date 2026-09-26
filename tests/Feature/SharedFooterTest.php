<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class SharedFooterTest extends TestCase
{
    public function test_footer_renders_brand_tagline_and_legal_items(): void
    {
        config([
            'bilihub.tagline' => 'Your marketplace, delivered.',
            'bilihub.social.facebook' => null,
            'bilihub.social.instagram' => null,
        ]);

        $html = Blade::render('<x-footer />');

        $this->assertStringContainsString('<footer class="bili-footer', $html);
        $this->assertStringContainsString('images/bilihublogo.png', $html);
        $this->assertStringContainsString('bili-footer__name">BiliHub', $html);
        $this->assertStringContainsString('Your marketplace, delivered.', $html);
        $this->assertStringContainsString('bili-footer__divider', $html);

        $text = html_entity_decode($html);

        $this->assertStringContainsString('© ' . date('Y') . ' BiliHub. All rights reserved.', $text);

        foreach (['Privacy Policy', 'Terms & Conditions', 'Cookie Policy'] as $label) {
            $this->assertStringContainsString($label, $text);
        }
    }

    public function test_footer_hides_social_buttons_until_a_url_is_configured(): void
    {
        config([
            'bilihub.social.facebook' => null,
            'bilihub.social.instagram' => null,
        ]);

        $html = Blade::render('<x-footer />');

        $this->assertStringNotContainsString('bili-footer__social', $html);
        $this->assertStringNotContainsString('bi-twitter-x', $html);

        config([
            'bilihub.social.facebook' => 'https://facebook.com/bilihub',
            'bilihub.social.instagram' => 'https://instagram.com/bilihub',
        ]);

        $html = Blade::render('<x-footer />');

        $this->assertStringContainsString('bili-footer__social-link', $html);
        $this->assertStringContainsString('aria-label="BiliHub on Facebook"', $html);
        $this->assertStringContainsString('aria-label="BiliHub on Instagram"', $html);
        $this->assertStringContainsString('rel="noopener noreferrer"', $html);
    }

    public function test_footer_never_renders_dead_legal_links(): void
    {
        $html = Blade::render('<x-footer />');

        // No legal route exists yet, so the labels must be inert placeholders
        // instead of links that go nowhere.
        $this->assertStringNotContainsString('href="#"', $html);
    }

    public function test_bilihub_layouts_use_the_shared_footer_component(): void
    {
        $layouts = [
            'layouts/app.blade.php',
            'seller/layout.blade.php',
            'admin/layout.blade.php',
            'rider/layout.blade.php',
        ];

        foreach ($layouts as $layout) {
            $contents = file_get_contents(resource_path('views/' . $layout));

            $this->assertStringContainsString('<x-footer />', $contents, "{$layout} must render the shared footer");
            $this->assertStringNotContainsString('<footer', $contents, "{$layout} must not duplicate footer markup");
            $this->assertStringContainsString('resources/css/components/footer.css', $contents, "{$layout} must load the shared footer stylesheet");
        }
    }

    public function test_footer_stylesheet_lives_in_one_shared_file(): void
    {
        $shared = file_get_contents(resource_path('css/components/footer.css'));

        $this->assertStringContainsString('.bili-footer', $shared);

        $this->assertStringNotContainsString('.bili-footer', file_get_contents(resource_path('css/app.css')));
        $this->assertStringNotContainsString('.bili-footer', file_get_contents(resource_path('css/admin/admin-layout.css')));
        $this->assertStringNotContainsString('.bili-footer', file_get_contents(resource_path('css/seller/seller-layout.css')));
        $this->assertStringNotContainsString('.bili-footer', file_get_contents(resource_path('css/seller/dashboard.css')));
        $this->assertStringNotContainsString('.bili-footer', file_get_contents(resource_path('css/rider/rider-layout.css')));
    }
}
