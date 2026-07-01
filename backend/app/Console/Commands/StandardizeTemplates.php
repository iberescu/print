<?php

namespace App\Console\Commands;

use App\Models\Template;
use Illuminate\Console\Command;

/**
 * Every business-card template gets a consistent structure:
 *  - the monogram / real logo is replaced with the "your logo here" placeholder
 *  - company name / your name / role / url / phone fields are tagged (rmpRole)
 *  - the email line becomes a company-url placeholder
 */
class StandardizeTemplates extends Command
{
    protected $signature = 'templates:standardize';
    protected $description = 'Give every business-card template a logo placeholder + tagged company/url/phone/name/role fields';

    private const SRC = '/storage/brand/logo-placeholder.webp';
    private const PW = 512;
    private const PH = 279;

    public function handle(): int
    {
        $n = 0;
        foreach (Template::where('category', 'business-cards')->get() as $t) {
            $data = $t->data;
            $data['objects'] = $this->transform($data['objects'] ?? []);
            $t->data = $data;
            $t->save();
            $n++;
        }
        $this->info("Standardized {$n} templates.");

        return self::SUCCESS;
    }

    private function transform(array $objects): array
    {
        $emailIdx = $phoneIdx = $monogramIdx = $imageIdx = null;
        $content = []; // company / name / role text indices, in document order

        foreach ($objects as $i => $o) {
            $type = strtolower($o['type'] ?? '');
            if ($type === 'image' && $imageIdx === null) {
                $imageIdx = $i;
                continue;
            }
            if (! in_array($type, ['itext', 'i-text', 'text', 'textbox'], true)) {
                continue;
            }
            $txt = trim((string) ($o['text'] ?? ''));
            if ($txt === '') {
                continue;
            }
            if (str_contains($txt, '@')) {
                $emailIdx = $i;
            } elseif ($this->isPhone($txt)) {
                $phoneIdx = $i;
            } elseif ($monogramIdx === null && preg_match('/^[A-Za-z&.]{1,3}$/', $txt)) {
                $monogramIdx = $i;
            } else {
                $content[] = $i;
            }
        }

        // tag the text fields
        foreach (['companyName', 'name', 'title'] as $k => $role) {
            if (isset($content[$k])) {
                $objects[$content[$k]]['rmpRole'] = $role;
            }
        }
        if ($emailIdx !== null) {
            $objects[$emailIdx]['rmpRole'] = 'url';
            $objects[$emailIdx]['text'] = $this->urlFrom((string) $objects[$emailIdx]['text']);
        }
        if ($phoneIdx !== null) {
            $objects[$phoneIdx]['rmpRole'] = 'phone';
        }

        // logo anchor + objects to drop (monogram text, its badge, any real logo image)
        $monoBB = $monogramIdx !== null ? $this->bbox($objects[$monogramIdx]) : null;
        $badgeIdx = $monoBB ? $this->findBadge($objects, $monoBB, $monogramIdx) : null;
        $remove = array_filter([$monogramIdx, $badgeIdx, $imageIdx], fn ($x) => $x !== null);

        $anchor = $imageIdx !== null ? $this->bbox($objects[$imageIdx])
            : ($badgeIdx !== null ? $this->bbox($objects[$badgeIdx])
            : ($monoBB ?: ['cx' => 640, 'cy' => 110, 'w' => 150, 'h' => 82]));

        $objects = array_values(array_filter($objects, fn ($o, $i) => ! in_array($i, $remove, true), ARRAY_FILTER_USE_BOTH));

        // place the placeholder, centred on the anchor
        $targetW = max(100, min(210, $anchor['w']));
        $scale = $targetW / self::PW;
        $objects[] = [
            'type'        => 'Image',
            'version'     => '6.0.0',
            'src'         => self::SRC,
            'crossOrigin' => 'anonymous',
            'left'        => round($anchor['cx'], 2),
            'top'         => round($anchor['cy'], 2),
            'originX'     => 'center',
            'originY'     => 'center',
            'width'       => self::PW,
            'height'      => self::PH,
            'scaleX'      => round($scale, 4),
            'scaleY'      => round($scale, 4),
            'rmpRole'     => 'logo',
        ];

        return $objects;
    }

    private function isPhone(string $t): bool
    {
        return preg_match('/\d/', $t) && (bool) preg_match('/[+()\-.]|\d{3}/', $t) && ! preg_match('/[a-zA-Z]{4,}/', $t);
    }

    private function urlFrom(string $email): string
    {
        return preg_match('/@([^\s]+)/', $email, $m) ? 'www.'.strtolower(trim($m[1])) : 'www.yourcompany.com';
    }

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

    private function findBadge(array $objects, array $mb, int $skip): ?int
    {
        $best = null;
        $bestDist = 70;
        foreach ($objects as $i => $o) {
            if ($i === $skip) {
                continue;
            }
            if (! in_array(strtolower($o['type'] ?? ''), ['circle', 'rect'], true)) {
                continue;
            }
            $b = $this->bbox($o);
            if ($b['w'] > 260 || $b['h'] > 260) {
                continue; // skip big background panels
            }
            $d = hypot($b['cx'] - $mb['cx'], $b['cy'] - $mb['cy']);
            if ($d < $bestDist) {
                $bestDist = $d;
                $best = $i;
            }
        }

        return $best;
    }
}
