<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Support\PrintSpec;
use PHPUnit\Framework\TestCase;

class PrintSpecTest extends TestCase
{
    private function product(string $slug = 'flyers'): Product
    {
        return (new Product)->forceFill(['slug' => $slug]);
    }

    public function test_inch_quoted_size_labels_parse(): void
    {
        // regression: '8.5" x 11"' didn't parse (the quote broke the regex) so
        // selecting a size never changed the canvas
        $this->assertTrue(PrintSpec::parsesAsSize('8.5" x 11"', $this->product()));
        $this->assertTrue(PrintSpec::parsesAsSize('11" x 17"', $this->product()));
        $this->assertTrue(PrintSpec::parsesAsSize('3.5×2"', $this->product()));
        $this->assertTrue(PrintSpec::parsesAsSize('2×4 ft', $this->product()));
    }

    public function test_named_and_single_dimension_sizes_parse(): void
    {
        $this->assertTrue(PrintSpec::parsesAsSize('A4', $this->product()));
        $this->assertTrue(PrintSpec::parsesAsSize('DL', $this->product()));
        $this->assertTrue(PrintSpec::parsesAsSize('2"', $this->product()));
    }

    public function test_non_size_labels_do_not_parse(): void
    {
        $this->assertFalse(PrintSpec::parsesAsSize('Standard', $this->product()));
        $this->assertFalse(PrintSpec::parsesAsSize('Premium Matte', $this->product()));
        $this->assertFalse(PrintSpec::parsesAsSize('Glossy', $this->product()));
    }
}
