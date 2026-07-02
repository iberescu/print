<?php

namespace Tests\Feature;

use App\Support\PreviewStore;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    public function test_unsigned_stripe_webhook_is_rejected_in_production(): void
    {
        config(['shop.stripe.webhook_secret' => null]);
        $this->app['env'] = 'production';

        $this->postJson('/stripe/webhook', ['type' => 'checkout.session.completed'])
            ->assertStatus(400);

        $this->app['env'] = 'testing';
    }

    public function test_unsigned_stripe_webhook_allowed_in_local_dev_only(): void
    {
        config(['shop.stripe.webhook_secret' => null]);

        $this->postJson('/stripe/webhook', ['type' => 'noop'])->assertOk();
    }

    public function test_preview_store_persists_data_urls_and_rejects_junk(): void
    {
        Storage::fake('public');

        // 1×1 px jpeg
        $dataUrl = 'data:image/jpeg;base64,'.base64_encode(base64_decode(
            '/9j/4AAQSkZJRgABAQEAAAAAAAD/2wBDAAMCAgICAgMCAgIDAwMDBAYEBAQEBAgGBgUGCQgKCgkICQkKDA8MCgsOCwkJDRENDg8QEBEQCgwSExIQEw8QEBD/yQALCAABAAEBAREA/8wABgAQEAX/2gAIAQEAAD8A0s8g/9k='
        ));
        $url = PreviewStore::persist($dataUrl);
        $this->assertNotNull($url);
        $this->assertStringContainsString('/storage/previews/', $url);

        // already-stored URLs pass through untouched (Review posts them back)
        $this->assertSame('/storage/previews/x.jpg', PreviewStore::persist('/storage/previews/x.jpg'));

        // junk and non-image payloads are rejected
        $this->assertNull(PreviewStore::persist('data:text/html;base64,PHNjcmlwdD4='));
        $this->assertNull(PreviewStore::persist('<script>alert(1)</script>'));
    }
}
