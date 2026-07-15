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

    private function surface(array $attrs): \App\Models\Surface
    {
        return (new \App\Models\Surface)->forceFill($attrs + [
            'unit' => 'in', 'bleed' => 0.13, 'safety' => 0.13,
            'no_print_areas' => [], 'fold_lines' => [], 'cut_path' => null,
            'name' => 't', 'slug' => 't',
        ]);
    }

    public function test_guide_surface_folds_project_onto_a_foldless_spec(): void
    {
        // regression: the parsed-size-label path hardcodes fold: [] and the guide
        // merge only carried margins + cut, so no folded product showed fold lines
        $spec = PrintSpec::fromSurface($this->surface(['width' => 5.0, 'height' => 7.0]));
        $guides = $this->surface([
            'width' => 5.0, 'height' => 7.0,
            'fold_lines' => [['label' => 'Fold', 'orientation' => 'vertical', 'position' => 2.5]],
        ]);

        $merged = PrintSpec::withGuidesFrom($spec, $guides);

        $this->assertCount(1, $merged['fold']);
        $this->assertSame('vertical', $merged['fold'][0]['orientation']);
        $this->assertSame($merged['bleed'] + (int) round(0.5 * $merged['trimW']), $merged['fold'][0]['pos']);
        $this->assertSame('Fold', $merged['fold'][0]['label']);
    }

    public function test_folds_rotate_when_the_guide_surface_is_the_other_orientation(): void
    {
        // tri-fold brochures: the fold surface is 11×8.5 (landscape) but the
        // selected size label is 8.5" x 11" (portrait) — the fold across the
        // sheet's long edge must stay across the long edge
        $spec = PrintSpec::fromSurface($this->surface(['width' => 8.5, 'height' => 11.0]));
        $guides = $this->surface([
            'width' => 11.0, 'height' => 8.5,
            'fold_lines' => [['label' => 'Fold', 'orientation' => 'vertical', 'position' => 5.5]],
        ]);

        $merged = PrintSpec::withGuidesFrom($spec, $guides);

        $this->assertCount(1, $merged['fold']);
        $this->assertSame('horizontal', $merged['fold'][0]['orientation']);
        $this->assertSame($merged['bleed'] + (int) round(0.5 * $merged['trimH']), $merged['fold'][0]['pos']);
    }

    public function test_square_guide_surfaces_never_rotate_their_folds(): void
    {
        // presentation folders: a square die (307×307 mm) under a 6"×9" selection
        $spec = PrintSpec::fromSurface($this->surface(['width' => 6.0, 'height' => 9.0]));
        $guides = $this->surface([
            'unit' => 'mm', 'width' => 307.38, 'height' => 307.38, 'bleed' => 1.91, 'safety' => 1.91,
            'fold_lines' => [
                ['label' => 'Fold', 'orientation' => 'vertical', 'position' => 153.69],
                ['label' => 'Fold', 'orientation' => 'horizontal', 'position' => 228.6],
            ],
        ]);

        $merged = PrintSpec::withGuidesFrom($spec, $guides);

        $this->assertSame('vertical', $merged['fold'][0]['orientation']);
        $this->assertSame($merged['bleed'] + (int) round(0.5 * $merged['trimW']), $merged['fold'][0]['pos']);
        $this->assertSame('horizontal', $merged['fold'][1]['orientation']);
        $this->assertSame($merged['bleed'] + (int) round(228.6 / 307.38 * $merged['trimH']), $merged['fold'][1]['pos']);
    }

    public function test_specs_with_their_own_folds_keep_them(): void
    {
        $spec = PrintSpec::fromSurface($this->surface([
            'width' => 5.0, 'height' => 7.0,
            'fold_lines' => [['label' => 'Own', 'orientation' => 'vertical', 'position' => 1.0]],
        ]));
        $guides = $this->surface([
            'width' => 5.0, 'height' => 7.0,
            'fold_lines' => [['label' => 'Donor', 'orientation' => 'vertical', 'position' => 2.5]],
        ]);

        $merged = PrintSpec::withGuidesFrom($spec, $guides);

        $this->assertCount(1, $merged['fold']);
        $this->assertSame('Own', $merged['fold'][0]['label']);
    }

    public function test_no_print_zones_project_with_axis_swap_on_rotation(): void
    {
        // a full-height pole sleeve on the left of a portrait donor becomes a
        // full-width band on a landscape spec
        $spec = PrintSpec::fromSurface($this->surface(['width' => 7.5, 'height' => 2.4]));
        $guides = $this->surface([
            'width' => 2.4, 'height' => 7.5,
            'no_print_areas' => [['label' => 'Pole sleeve', 'x' => 0, 'y' => 0, 'w' => 0.3, 'h' => 7.5]],
        ]);

        $merged = PrintSpec::withGuidesFrom($spec, $guides);

        $this->assertCount(1, $merged['noPrint']);
        $z = $merged['noPrint'][0];
        $this->assertSame($merged['trimW'], $z['w']);                          // full width now
        $this->assertSame((int) round(0.3 / 2.4 * $merged['trimH']), $z['h']); // sleeve depth on the other axis
        $this->assertSame('Pole sleeve', $z['label']);
    }
}
