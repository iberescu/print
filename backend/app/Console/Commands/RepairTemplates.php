<?php

namespace App\Console\Commands;

use App\Models\Template;
use Illuminate\Console\Command;

/**
 * Geometry/legibility repair pass for business-card templates, driven by the
 * 2026-07-15 Gemini render review. Four rule classes, all conservative:
 *
 *  1. logo slot: must sit inside the safe area and never overlap text — when it
 *     does (the standardizer centred it on the old monogram anchor, which put it
 *     over wordmarks/contacts on some layouts), relocate it to the nearest clear
 *     spot on a 3×3 safe-area grid (shrinking once if nothing fits).
 *  2. background panels: a rect ending within 14 px of a canvas edge is a math
 *     slip, not a design margin — snap that side to the edge (kills the white
 *     slivers that would show after trimming).
 *  3. contrast: text whose fill is near-invisible against its backing panel is
 *     lifted to a legible tone for that background.
 *  4. thin dividers: text colliding with a hairline gets nudged clear.
 *
 * The white logo-placeholder look itself is accepted by design (product-owner
 * call) — only its geometry is corrected here.
 */
class RepairTemplates extends Command
{
    protected $signature = 'templates:repair {--refs= : comma-separated refs (default: all business-cards)} {--issues= : JSON file of ref=>review issues; logo relocation only runs when the review implicates the logo} {--dry : report without saving}';

    protected $description = 'Repair BC template geometry/legibility defects (logo overlap, edge gaps, contrast, divider collisions)';

    private const W = 786;
    private const H = 460;
    private const BLEED = 13;
    private const SAFE = 13 + 13; // bleed + safety inset

    public function handle(): int
    {
        $refs = array_filter(array_map('trim', explode(',', (string) $this->option('refs'))));
        $issues = [];
        if ($f = $this->option('issues')) {
            $issues = json_decode((string) file_get_contents($f), true) ?: [];
        }
        $q = Template::where('category', 'business-cards');
        if ($refs) {
            $q->whereIn('ref', $refs);
        }

        $changed = 0;
        foreach ($q->get() as $t) {
            $log = [];
            $data = $t->data;
            $objects = $data['objects'] ?? [];
            if (! $objects) {
                continue;
            }

            $objects = $this->snapPanels($objects, $log);
            $objects = $this->fixContrast($objects, $data['background'] ?? '#ffffff', $log);
            $objects = $this->unclipTexts($objects, $log);
            $objects = $this->nudgeOffDividers($objects, $log);
            $reviewText = implode(' ', $issues[$t->ref] ?? []);
            $logoFlagged = $issues === [] || (bool) preg_match('/(overlap|obscur|cover|clip|cut ?off|misalign)[^.]*(logo|placeholder)|(logo|placeholder)[^.]*(overlap|obscur|cover|clip|cut ?off|misalign)/i', $reviewText);
            if ($logoFlagged) {
                $force = (bool) preg_match('/clip|cut ?off|trim|off the (bottom|card)|past the/i', $reviewText);
                $objects = $this->relocateLogo($objects, $log, $force);
            }

            if ($log) {
                $changed++;
                $this->line("{$t->ref}: ".implode(' | ', $log));
                if (! $this->option('dry')) {
                    $data['objects'] = $objects;
                    $t->data = $data;
                    $t->save();
                }
            }
        }
        $this->info(($this->option('dry') ? '[dry] ' : '')."{$changed} template(s) needed repairs.");

        return self::SUCCESS;
    }

    // ---- rule 1: logo slot ---------------------------------------------------

    private function relocateLogo(array $objects, array &$log, bool $force = false): array
    {
        $logoIdx = null;
        foreach ($objects as $i => $o) {
            if (($o['rmpRole'] ?? '') === 'logo' && strtolower($o['type'] ?? '') === 'image') {
                $logoIdx = $i;
            }
        }
        if ($logoIdx === null) {
            return $objects;
        }

        // the placeholder paints ~12% larger than its serialized box (asset
        // padding + fabric stroke) — inflate before any edge/overlap math
        $logo = $this->inflate($this->bbox($objects[$logoIdx]), 1.12);
        $others = [];
        foreach ($objects as $i => $o) {
            if ($i === $logoIdx) {
                continue;
            }
            $type = strtolower($o['type'] ?? '');
            if (in_array($type, ['itext', 'i-text', 'text', 'textbox', 'image'], true)) {
                $others[] = $this->glyphBox($o);
            } elseif (in_array($type, ['rect', 'line', 'circle', 'triangle', 'path', 'polygon'], true)) {
                $b = $this->bbox($o);
                // decorative accents/dividers must stay clear too — but not the
                // big background panels the slot legitimately sits on
                if ($b['w'] * $b['h'] < 0.30 * self::W * self::H) {
                    $others[] = $b;
                }
            }
        }

        $bad = $force || $this->outsideSafe($logo) || $this->hitsAny($logo, $others, 4);
        if (! $bad) {
            return $objects;
        }

        foreach ([1.0, 110 / max($logo['w'], 1), 92 / max($logo['w'], 1)] as $shrink) {
            $w = $logo['w'] * $shrink; // already inflated — grid math stays conservative
            $h = $logo['h'] * $shrink;
            $spot = $this->bestSpot($w, $h, $logo, $others);
            if ($spot) {
                $objects[$logoIdx]['left'] = round($spot['cx'], 2);
                $objects[$logoIdx]['top'] = round($spot['cy'], 2);
                $objects[$logoIdx]['originX'] = 'center';
                $objects[$logoIdx]['originY'] = 'center';
                if ($shrink < 1.0) {
                    $objects[$logoIdx]['scaleX'] = round(($objects[$logoIdx]['scaleX'] ?? 1) * $shrink, 4);
                    $objects[$logoIdx]['scaleY'] = round(($objects[$logoIdx]['scaleY'] ?? 1) * $shrink, 4);
                }
                $log[] = sprintf('logo slot moved to %d,%d%s', $spot['cx'], $spot['cy'], $shrink < 1 ? ' (shrunk)' : '');

                return $objects;
            }
        }
        $log[] = 'logo slot overlaps but NO clear spot found (manual)';

        return $objects;
    }

    private function inflate(array $b, float $f): array
    {
        $w = $b['w'] * $f;
        $h = $b['h'] * $f;

        return ['x' => $b['cx'] - $w / 2, 'y' => $b['cy'] - $h / 2, 'w' => $w, 'h' => $h, 'cx' => $b['cx'], 'cy' => $b['cy']];
    }

    /** A text's PAINTED extent: fabric textbox frames are often far wider than
     *  the glyphs, which made the overlap test fire on visually clear layouts.
     *  Estimate glyph width from length × fontSize and centre it in the frame. */
    private function glyphBox(array $o): array
    {
        $b = $this->bbox($o);
        if (! in_array(strtolower($o['type'] ?? ''), ['itext', 'i-text', 'text', 'textbox'], true)) {
            return $b;
        }
        $lines = preg_split('/\r?\n/', (string) ($o['text'] ?? ''));
        $chars = max(1, max(array_map('mb_strlen', $lines ?: [''])));
        $est = $chars * (($o['fontSize'] ?? 16) * ($o['scaleX'] ?? 1)) * 0.62;
        if ($est < $b['w']) {
            $b['x'] += ($b['w'] - $est) / 2;
            $b['w'] = $est;
            $b['cx'] = $b['x'] + $b['w'] / 2;
        }

        return $b;
    }

    /** Nearest clear centre on a 3×3 grid inside the safe area. */
    private function bestSpot(float $w, float $h, array $from, array $others): ?array
    {
        // top + middle rows only: rendered slot boxes run ~15px taller than the
        // JSON bbox, so bottom-row placements grazed the trim line in print
        $xs = [self::SAFE + $w / 2 + 30, self::W * 0.35, self::W / 2, self::W * 0.65, self::W - self::SAFE - $w / 2 - 30];
        $ys = [self::SAFE + $h / 2 + 20, self::H / 2 - 10];
        $best = null;
        foreach ($ys as $cy) {
            foreach ($xs as $cx) {
                $cand = ['x' => $cx - $w / 2, 'y' => $cy - $h / 2, 'w' => $w, 'h' => $h, 'cx' => $cx, 'cy' => $cy];
                if ($this->outsideSafe($cand) || $this->hitsAny($cand, $others, 8)) {
                    continue;
                }
                $d = hypot($cx - $from['cx'], $cy - $from['cy']);
                if (! $best || $d < $best['d']) {
                    $best = $cand + ['d' => $d];
                }
            }
        }

        return $best;
    }

    private function outsideSafe(array $b): bool
    {
        return $b['x'] < self::SAFE || $b['y'] < self::SAFE
            || $b['x'] + $b['w'] > self::W - self::SAFE || $b['y'] + $b['h'] > self::H - self::SAFE;
    }

    private function hitsAny(array $b, array $others, float $pad): bool
    {
        foreach ($others as $o) {
            if ($b['x'] < $o['x'] + $o['w'] + $pad && $b['x'] + $b['w'] > $o['x'] - $pad
                && $b['y'] < $o['y'] + $o['h'] + $pad && $b['y'] + $b['h'] > $o['y'] - $pad) {
                return true;
            }
        }

        return false;
    }

    // ---- rule 2: panel edge gaps ----------------------------------------------

    private function snapPanels(array $objects, array &$log): array
    {
        foreach ($objects as $i => $o) {
            if (strtolower($o['type'] ?? '') !== 'rect' || ! empty($o['rmpRole'])) {
                continue;
            }
            $b = $this->bbox($o);
            if ($b['w'] < 30 || $b['h'] < 30) {
                continue; // accents/dividers — rule 4 territory
            }
            $sx = $o['scaleX'] ?? 1;
            $sy = $o['scaleY'] ?? 1;
            $snapped = [];

            $gapL = $b['x'];
            $gapR = self::W - ($b['x'] + $b['w']);
            $gapT = $b['y'];
            $gapB = self::H - ($b['y'] + $b['h']);

            if ($gapL > 0 && $gapL <= 14) {
                $b['x'] = 0;
                $b['w'] += $gapL;
                $snapped[] = 'left';
            }
            if ($gapR > 0 && $gapR <= 14) {
                $b['w'] += $gapR;
                $snapped[] = 'right';
            }
            if ($gapT > 0 && $gapT <= 14) {
                $b['y'] = 0;
                $b['h'] += $gapT;
                $snapped[] = 'top';
            }
            if ($gapB > 0 && $gapB <= 14) {
                $b['h'] += $gapB;
                $snapped[] = 'bottom';
            }
            if (! $snapped) {
                continue;
            }

            // write back as a top-left–origin rect at the new bbox
            $objects[$i]['originX'] = 'left';
            $objects[$i]['originY'] = 'top';
            $objects[$i]['left'] = round($b['x'], 2);
            $objects[$i]['top'] = round($b['y'], 2);
            $objects[$i]['width'] = round($b['w'] / max($sx, 0.0001), 2);
            $objects[$i]['height'] = round($b['h'] / max($sy, 0.0001), 2);
            $log[] = 'panel snapped: '.implode('/', $snapped);
        }

        return $objects;
    }

    // ---- rule 3: contrast -------------------------------------------------------

    private function fixContrast(array $objects, string $canvasBg, array &$log): array
    {
        foreach ($objects as $i => $o) {
            if (! in_array(strtolower($o['type'] ?? ''), ['itext', 'i-text', 'text', 'textbox'], true)) {
                continue;
            }
            $fill = $this->rgb($o['fill'] ?? null);
            if (! $fill) {
                continue;
            }
            $b = $this->glyphBox($o);
            $bg = $this->rgb($canvasBg) ?: [255, 255, 255];
            $bestCover = 0.0;
            foreach ($objects as $j => $p) {
                if ($j >= $i || strtolower($p['type'] ?? '') !== 'rect') {
                    continue;
                }
                $pb = $this->bbox($p);
                $c = $this->rgb($p['fill'] ?? null);
                if (! $c) {
                    continue;
                }
                $ix = max(0, min($b['x'] + $b['w'], $pb['x'] + $pb['w']) - max($b['x'], $pb['x']));
                $iy = max(0, min($b['y'] + $b['h'], $pb['y'] + $pb['h']) - max($b['y'], $pb['y']));
                $cover = ($ix * $iy) / max(1, $b['w'] * $b['h']);
                // topmost panel covering most of the text wins (ties → later object)
                if ($cover >= 0.6 && $cover >= $bestCover) {
                    $bestCover = $cover;
                    $bg = $c;
                }
            }
            if ($this->contrast($fill, $bg) >= 2.8) {
                continue;
            }
            $objects[$i]['fill'] = $this->lum($bg) < 0.4 ? '#E7ECF2' : '#233043';
            $log[] = 'contrast lifted: "'.mb_substr(trim((string) ($o['text'] ?? '')), 0, 24).'"';
        }

        return $objects;
    }

    // ---- rule 3b: text clipped at the right trim -------------------------------

    private function unclipTexts(array $objects, array &$log): array
    {
        foreach ($objects as $i => $o) {
            if (! in_array(strtolower($o['type'] ?? ''), ['itext', 'i-text', 'text', 'textbox'], true)) {
                continue;
            }
            $b = $this->glyphBox($o);
            $over = ($b['x'] + $b['w']) - (self::W - self::SAFE);
            if ($over > 0 && $over <= 48 && $b['x'] - $over >= self::SAFE) {
                $objects[$i]['left'] = round(($objects[$i]['left'] ?? 0) - $over, 2);
                $log[] = 'text pulled inside right safe edge: "'.mb_substr(trim((string) ($o['text'] ?? '')), 0, 20).'"';
            }
        }

        return $objects;
    }

    // ---- rule 4: divider collisions ---------------------------------------------

    private function nudgeOffDividers(array $objects, array &$log): array
    {
        $thin = [];
        foreach ($objects as $o) {
            if (in_array(strtolower($o['type'] ?? ''), ['rect', 'line'], true)) {
                $b = $this->bbox($o);
                if (($b['w'] <= 5 && $b['h'] > 30) || ($b['h'] <= 5 && $b['w'] > 30)) {
                    $thin[] = $b;
                }
            }
        }
        if (! $thin) {
            return $objects;
        }

        foreach ($objects as $i => $o) {
            if (! in_array(strtolower($o['type'] ?? ''), ['itext', 'i-text', 'text', 'textbox'], true)) {
                continue;
            }
            $b = $this->bbox($o);
            foreach ($thin as $d) {
                $vertical = $d['w'] <= 5;
                if (! $this->hitsAny($b, [$d], 0)) {
                    continue;
                }
                if ($vertical) {
                    $overlap = ($b['x'] + $b['w']) - $d['x'];
                    if ($overlap > 0 && $overlap <= 24 && $b['x'] - $overlap - 8 >= self::SAFE) {
                        $objects[$i]['left'] = round(($objects[$i]['left'] ?? 0) - $overlap - 8, 2);
                        $log[] = 'text nudged off divider: "'.mb_substr(trim((string) ($o['text'] ?? '')), 0, 20).'"';
                    }
                } else {
                    $overlap = ($b['y'] + $b['h']) - $d['y'];
                    if ($overlap > 0 && $overlap <= 16) {
                        $objects[$i]['top'] = round(($objects[$i]['top'] ?? 0) - $overlap - 6, 2);
                        $log[] = 'text nudged off divider (v): "'.mb_substr(trim((string) ($o['text'] ?? '')), 0, 20).'"';
                    }
                }
            }
        }

        return $objects;
    }

    // ---- helpers -------------------------------------------------------------

    private function bbox(array $o): array
    {
        $sx = $o['scaleX'] ?? 1;
        $sy = $o['scaleY'] ?? 1;
        if (strtolower($o['type'] ?? '') === 'circle') {
            $w = 2 * ($o['radius'] ?? 0) * $sx;
            $h = 2 * ($o['radius'] ?? 0) * $sy;
        } else {
            $w = ($o['width'] ?? 0) * $sx;
            $h = ($o['height'] ?? 0) * $sy;
        }
        $left = $o['left'] ?? 0;
        $top = $o['top'] ?? 0;
        $ox = $o['originX'] ?? 'left';
        $oy = $o['originY'] ?? 'top';
        $x = $left - ($ox === 'center' ? $w / 2 : ($ox === 'right' ? $w : 0));
        $y = $top - ($oy === 'center' ? $h / 2 : ($oy === 'bottom' ? $h : 0));

        return ['x' => $x, 'y' => $y, 'w' => $w, 'h' => $h, 'cx' => $x + $w / 2, 'cy' => $y + $h / 2];
    }

    /** @return array{0:int,1:int,2:int}|null */
    private function rgb($fill): ?array
    {
        if (! is_string($fill)) {
            return null;
        }
        $f = trim($fill);
        if (preg_match('/^#([0-9a-f]{3})$/i', $f, $m)) {
            return [hexdec(str_repeat($m[1][0], 2)), hexdec(str_repeat($m[1][1], 2)), hexdec(str_repeat($m[1][2], 2))];
        }
        if (preg_match('/^#([0-9a-f]{6})/i', $f, $m)) {
            return [hexdec(substr($m[1], 0, 2)), hexdec(substr($m[1], 2, 2)), hexdec(substr($m[1], 4, 2))];
        }
        if (preg_match('/^rgba?\((\d+)[,\s]+(\d+)[,\s]+(\d+)(?:[,\s\/]+([0-9.]+))?/i', $f, $m)) {
            // a mostly-transparent wash doesn't determine the text's background
            if (isset($m[4]) && $m[4] !== '' && (float) $m[4] < 0.5) {
                return null;
            }
            return [(int) $m[1], (int) $m[2], (int) $m[3]];
        }

        return null;
    }

    private function lum(array $rgb): float
    {
        $c = array_map(function ($v) {
            $v /= 255;

            return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        }, $rgb);

        return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
    }

    private function contrast(array $a, array $b): float
    {
        $l1 = $this->lum($a);
        $l2 = $this->lum($b);

        return (max($l1, $l2) + 0.05) / (min($l1, $l2) + 0.05);
    }
}
