<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Crawl artifact: a few metric surfaces were imported with fold positions in
 * inches (e.g. a fold at "6.05" on the 307.38 mm presentation-folder die —
 * 6.05 in = 153.7 mm, dead centre). Convert any fold that hugs the edge
 * (<5% of its axis) but lands plausibly when read as inches. ImportCatalog
 * now applies the same normalization, so fresh imports stay consistent.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('surfaces')->whereIn('unit', ['mm', 'cm'])->whereNotNull('fold_lines')->get() as $s) {
            $folds = json_decode($s->fold_lines, true);
            if (! is_array($folds) || $folds === []) {
                continue;
            }
            $factor = $s->unit === 'mm' ? 25.4 : 2.54;
            $changed = false;
            foreach ($folds as &$f) {
                if (! is_numeric($f['position'] ?? null)) {
                    continue;
                }
                $axis = (($f['orientation'] ?? 'vertical') !== 'horizontal') ? (float) $s->width : (float) $s->height;
                $pos = (float) $f['position'];
                if ($axis > 0 && $pos / $axis < 0.05) {
                    $conv = $pos * $factor;
                    if ($conv / $axis >= 0.05 && $conv / $axis <= 0.98) {
                        $f['position'] = round($conv, 2);
                        $changed = true;
                    }
                }
            }
            unset($f);
            if ($changed) {
                DB::table('surfaces')->where('id', $s->id)->update(['fold_lines' => json_encode($folds)]);
            }
        }
    }

    public function down(): void
    {
        // one-way data repair — the original mixed-unit values are not worth restoring
    }
};
