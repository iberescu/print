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

    public function test_curly_quote_sizes_parse(): void
    {
        // regression: the crawl wrote 2”x2” with U+201D quotes (qr-code-stickers,
        // wine-labels) and the shape wiring found no dims for those products
        $this->assertTrue(PrintSpec::parsesAsSize('2”x2”', $this->product()));
        $this->assertSame([2.0, 2.0], array_slice(PrintSpec::parsedDims('2” x 2”', $this->product()), 0, 2));
    }

    public function test_three_dimension_labels_do_not_parse(): void
    {
        // tower displays: "13" x 12" x 63.4"" is W×D×H — grabbing the first two
        // numbers would make a 13×12 print canvas out of a 43.5×63.4 tower
        $this->assertFalse(PrintSpec::parsesAsSize('13" x 12" x 63.4"', $this->product()));
        $this->assertFalse(PrintSpec::parsesAsSize('20 x 18 x 79', $this->product()));
    }

    public function test_guides_override_converts_units_and_shifts_offsets(): void
    {
        // an inch-based canvas taking margins from a mm die template (door hangers:
        // sizes parse as inches, the crawled cut surface is 2.98 mm)
        $surface = (new \App\Models\Surface)->forceFill([
            'unit' => 'in', 'width' => 3.5, 'height' => 8.5,
            'bleed' => 0.125, 'safety' => 0.125,
            'no_print_areas' => [['label' => 'Zone', 'x' => 0, 'y' => 0, 'w' => 1, 'h' => 1]],
            'fold_lines' => [], 'cut_path' => null, 'name' => 't', 'slug' => 't',
        ]);
        $spec = PrintSpec::fromSurface($surface);

        $guides = (new \App\Models\Surface)->forceFill(['unit' => 'mm', 'bleed' => 2.98, 'safety' => 2.98]);
        $merged = PrintSpec::withGuidesFrom($spec, $guides);

        // 2.98 mm = 0.1173", canvas is 760 px on the 8.5" edge → ≈ 10 px
        $this->assertSame(10, $merged['bleed']);
        $this->assertSame(10, $merged['safety']);
        $this->assertSame($merged['trimW'] + 2 * $merged['bleed'], $merged['w']);
        // no-print offsets ride on the bleed inset
        $shift = $merged['bleed'] - $spec['bleed'];
        $this->assertSame($spec['noPrint'][0]['x'] + $shift, $merged['noPrint'][0]['x']);
    }
}
