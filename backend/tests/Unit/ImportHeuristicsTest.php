<?php

namespace Tests\Unit;

use App\Console\Commands\ImportCatalog;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * The importer's pure heuristics: option-name derivation from values, the
 * per-unit-price correction on quantity tiers, and title-based categorization.
 */
class ImportHeuristicsTest extends TestCase
{
    private function invoke(string $method, ...$args)
    {
        $ref = new ReflectionMethod(ImportCatalog::class, $method);
        $ref->setAccessible(true);

        return $ref->invoke(new ImportCatalog, ...$args);
    }

    public function test_option_names_derive_from_values(): void
    {
        $cases = [
            'Orientation' => ['Horizontal', 'Vertical'],
            'Size'        => ['4" x 6"', '5" x 7"', '6" x 11"'],
            'Finish'      => ['Glossy', 'Matte', 'Uncoated', 'Recycled'],
            'Corners'     => ['Standard', 'Rounded'],
            'Material'    => ['White Paper', 'White Plastic (BOPP)'],
            'Foil'        => ['None', 'Gold', 'Rose Gold', 'Silver'],
            'Container'   => ['12 oz. Cans', '16 oz. Cans', '12 oz. Bottles'],
            'Shape'       => ['Rectangle/Square', 'Circle', 'Arrow', 'Hexagon'],
            'Thickness'   => ['Standard - 0.4 mm', 'Premium - 0.6 mmRecommended'],
            'Usage'       => ['Indoor', 'Outdoor'],
        ];
        foreach ($cases as $expected => $labels) {
            $this->assertSame($expected, $this->invoke('deriveOptionName', $labels), 'for '.implode('/', $labels));
        }
    }

    public function test_unrecognized_values_keep_the_placeholder(): void
    {
        $this->assertNull($this->invoke('deriveOptionName', ['Foo', 'Bar', 'Baz']));
    }

    public function test_falling_tier_totals_are_per_unit_prices_and_get_corrected(): void
    {
        // regression: Gemini captured per-unit prices as tier totals ("25 flyers = $0.36")
        $tiers = $this->invoke('tiers', [
            ['quantity' => 25, 'totalPrice' => 0.36],
            ['quantity' => 50, 'totalPrice' => 0.26],
            ['quantity' => 100, 'totalPrice' => 0.20],
        ]);
        $this->assertSame(9.0, $tiers[0]['total_price']);   // 25 × 0.36
        $this->assertSame(13.0, $tiers[1]['total_price']);  // 50 × 0.26
        $this->assertSame(20.0, $tiers[2]['total_price']);  // 100 × 0.20
    }

    public function test_rising_tier_totals_pass_through(): void
    {
        $tiers = $this->invoke('tiers', [
            ['quantity' => 50, 'totalPrice' => 10.0],
            ['quantity' => 100, 'totalPrice' => 14.99],
        ]);
        $this->assertSame(10.0, $tiers[0]['total_price']);
        $this->assertSame(14.99, $tiers[1]['total_price']);
    }

    public function test_categorization_from_title(): void
    {
        $cases = [
            ['Feather Flags', '', 'signs-banners'],
            ['Custom Posters', '', 'signs-banners'],          // regression: "signs-POSTERs" URL trap
            ['Metal Business Card Holder', '', 'accessories'], // holders are not printable cards
            ['Matte Business Cards', '', 'business-cards'],
            ['Bumper Stickers', '', 'stickers-labels'],
            ['Custom Pillows', '', 'other'],
        ];
        foreach ($cases as [$title, $vp, $expected]) {
            $this->assertSame($expected, $this->invoke('categorize', $title, $vp, 'other'), "for {$title}");
        }
    }

    public function test_shape_labels_classify(): void
    {
        $cases = [
            // "Standard" is the product's own format — never rewires the die
            // (regression: it flattened Rounded Corner BCs to plain rectangles)
            ['Standard', 'keep'],
            ['Rounded Rectangle', 'rounded'],
            ['Rounded Square', 'rounded'],
            ['Circle', 'ellipse'],
            ['Circle/Oval', 'ellipse'],
            ['Half Circle', 'half-circle'],
            ['Custom (Die-Cut)', 'flat'],
            ['Rectangle/Square', 'flat'],
            ['HeartNew', 'heart'],          // crawl badge glued to the label
            ['StarburstNew', 'starburst'],
            ['Full Arch', 'arch'],
            ['Half Left Arch', 'arch-left'],
            ['Graduation Cap', null],       // exotic die — left to the product default
            ['Dog Face 1', null],
        ];
        foreach ($cases as [$label, $expected]) {
            $this->assertSame($expected, ImportCatalog::classifyShape($label), "for {$label}");
        }
    }
}
