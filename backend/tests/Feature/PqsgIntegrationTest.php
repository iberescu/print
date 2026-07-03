<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PqsgIntegrationTest extends TestCase
{
    private function product(): Product
    {
        $category = Category::create(['name' => 'Business Cards', 'slug' => 'business-cards', 'is_active' => true, 'sort_order' => 0]);

        return Product::create([
            'category_id' => $category->id, 'name' => 'Test Cards', 'slug' => 'test-cards',
            'from_price' => 10, 'is_active' => true, 'supports_design' => true, 'supports_upload' => true, 'sort_order' => 0,
        ]);
    }

    public function test_designer_review_registers_a_capture_with_real_brand_data(): void
    {
        Http::fake(['*/capture' => Http::response(['uuid' => 'cap-123'])]);
        $product = $this->product();

        $this->post("/design/{$product->slug}/review", [
            'brand' => ['url' => 'acme-corp.com', 'logo' => null],
            'mode'  => 'design',
        ])->assertRedirect();

        Http::assertSent(function ($request) {
            return str_ends_with($request->url(), '/capture')
                && $request['website'] === 'https://acme-corp.com'
                && $request['source'] === 'runmyprint-designer'
                && ! empty($request['idempotency_key']);
        });

        $key = session('pqsg.key');
        $this->assertNotNull($key);
        $this->assertSame('cap-123', Cache::get("pqsg:{$key}"));
        $this->assertSame('cap-123', $this->getJson("/pqsg/status/{$key}")->json('uuid'));
    }

    public function test_seed_placeholders_are_never_sent_to_the_engine(): void
    {
        Http::fake();
        $product = $this->product();

        $this->post("/design/{$product->slug}/review", [
            'brand' => ['url' => 'yourcompany.com', 'logo' => '/storage/brand/logo-placeholder.webp'],
            'mode'  => 'design',
        ])->assertRedirect();

        Http::assertNothingSent();
        $this->assertNull(session('pqsg.key'));
    }

    public function test_artwork_upload_registers_a_pdf_capture(): void
    {
        Http::fake(['*/capture' => Http::response(['uuid' => 'cap-pdf'])]);
        Storage::fake('public');

        $resp = $this->post('/pqsg/upload', [
            'file' => UploadedFile::fake()->create('artwork.pdf', 200, 'application/pdf'),
        ])->assertOk();

        $key = $resp->json('key');
        $this->assertNotNull($key);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/capture')
            && ! empty($r['pdf_url'])
            && $r['source'] === 'runmyprint-upload');
        $this->assertSame('cap-pdf', Cache::get("pqsg:{$key}"));
    }

    public function test_status_rejects_non_uuid_keys(): void
    {
        $this->getJson('/pqsg/status/../etc/passwd')->assertNotFound();
        $this->getJson('/pqsg/status/notauuid')->assertNotFound();
    }
}
