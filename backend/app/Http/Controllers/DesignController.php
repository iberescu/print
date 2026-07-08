<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Template;
use App\Services\Pricing;
use App\Support\PrintSpec;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class DesignController extends Controller
{
    public function show(Product $product, Request $request): Response
    {
        abort_unless($product->is_active, 404);
        $product->load(['category', 'surface', 'options.values.surface']);

        $opts = $this->optionIds($request);

        // Every clean entry (blank editor, template pick) is a NEW project;
        // only an explicit ?project= resumes earlier work — and only when this
        // session owns that project (the id→file map lives in the session).
        $projectId = (string) $request->query('project', '');
        $saved = null;
        if ($projectId !== '') {
            // this session's map first, else a project the signed-in user owns
            $path = session('design.projects')[$projectId] ?? null;
            if (! $path && $request->user() && \Illuminate\Support\Str::isUuid($projectId)) {
                $path = \App\Models\DesignProject::where('id', $projectId)
                    ->where('user_id', $request->user()->id)->value('design_path');
            }
            if ($path && Storage::disk('public')->exists($path)) {
                $saved = json_decode((string) Storage::disk('public')->get($path), true) ?: null;
            }
        }
        if (! $saved) {
            $projectId = (string) \Illuminate\Support\Str::uuid();
        }

        return Inertia::render('Editor', [
            'product'   => $product->only('id', 'name', 'slug', 'decoration'),
            'category'  => ['name' => $product->category->name, 'slug' => $product->category->slug],
            'mode'      => $request->query('mode') === 'upload' ? 'upload' : 'design',
            'templates' => $this->templatesFor($product),
            'template'  => $request->query('template'),   // pre-apply this template ref (from the gallery)
            'project'   => $projectId,
            'savedDesign' => $saved,
            'canvas'    => $this->geometry($product, $opts),
            'selection' => [
                'quantityId'     => ((int) $request->query('qty')) ?: null,
                'optionValueIds' => $opts,
            ],
        ]);
    }

    /** Template gallery shown before the editor (req: pick a template first). */
    public function templates(Product $product, Request $request): Response
    {
        abort_unless($product->is_active && $product->supports_design, 404);
        $product->load(['category', 'surface', 'options.values.surface']);

        $opts = $this->optionIds($request);

        return Inertia::render('Templates', [
            'product'   => $product->only('id', 'name', 'slug'),
            'category'  => ['name' => $product->category->name, 'slug' => $product->category->slug],
            'templates' => $this->templatesFor($product),
            'canvas'    => $this->geometry($product, $opts),
            'selection' => [
                'quantityId'     => ((int) $request->query('qty')) ?: null,
                'optionValueIds' => $opts,
            ],
        ]);
    }

    /** The die-cut edge and the canvas dimensions resolve INDEPENDENTLY, then merge:
     *  a Shape value carries the cut (possibly none — "Rectangle" flattens a die-cut
     *  default), a Size/Format value carries the dims, a cut-bearing value on any
     *  other option (e.g. Corners → Rounded) still wins the cut. Bleed/safety come
     *  from the cut's surface — the die template's measured margins beat a size
     *  value's print-standard defaults. */
    private function geometry(Product $product, array $opts): array
    {
        $base = $product->surface;

        // Per option: the explicitly selected value, else its default (the product
        // page sends the full selection; template/deep links may send none).
        $picked = [];
        foreach ($product->options as $opt) {
            $v = $opt->values->first(fn ($v) => in_array($v->id, $opts, true))
                ?? $opt->values->first(fn ($v) => $v->is_default);
            if ($v) {
                $picked[] = [$opt, $v];
            }
        }

        $cut = $base?->cut_path;
        $cutSrc = $base;                // surface whose bleed/safety apply
        $dims = null;                   // plain value surface that sets the dims
        $shapeDims = null;              // a shape value's own surface can set dims too
        foreach ($picked as [$opt, $v]) {
            if (! $v->surface) {
                continue;
            }
            if (preg_match('/shape/i', $opt->name)) {
                // a Shape value ALWAYS owns the cut — an explicit flat shape
                // (Rectangle, Custom die-cut) clears an inherited die
                $cut = $v->surface->cut_path;
                $cutSrc = $v->surface;
                $shapeDims = $v->surface;
            } elseif ($v->surface->cut_path) {
                $cut = $v->surface->cut_path;   // Corners → Rounded etc.
                $cutSrc = $v->surface;
            } else {
                $dims ??= $v->surface;          // size/format/paper value
            }
        }

        // Dims: explicit value surface > parseable size label > shape surface > product.
        $spec = null;
        $specSrc = null;                // surface the spec was built from (null = parsed label)
        if ($dims) {
            $spec = PrintSpec::fromSurface($dims);
            $specSrc = $dims;
        } else {
            foreach ($picked as [$opt, $v]) {
                if (preg_match('/size|format|dimension/i', $opt->name) && ! $v->surface
                    && PrintSpec::parsesAsSize($v->label, $product)) {
                    $spec = PrintSpec::canvas($product, $opts);
                    break;
                }
            }
        }
        if (! $spec && $shapeDims) {
            $spec = PrintSpec::fromSurface($shapeDims);
            $specSrc = $shapeDims;
        }
        if (! $spec) {
            $spec = $base ? PrintSpec::fromSurface($base) : PrintSpec::canvas($product, $opts);
            $specSrc = $base;
        }

        $spec['cut'] = $cut;
        if ($cutSrc && $cutSrc->id !== $specSrc?->id) {
            $spec = PrintSpec::withGuidesFrom($spec, $cutSrc);
        }

        return $spec;
    }

    public function templateData(Template $template): JsonResponse
    {
        return response()->json(['data' => $template->data]);
    }

    /** Stash the finished design, then show the review step (PRG). */
    public function review(Product $product, Request $request): RedirectResponse
    {
        abort_unless($product->is_active, 404);

        $data = $request->validate([
            'preview'           => ['nullable', 'string', 'max:4000000'],
            // full fabric JSON (front/back) — uploaded logos ride along as data-URLs
            'design'            => ['nullable', 'string', 'max:12000000'],
            'project'           => ['nullable', 'uuid'],
            'brand'             => ['nullable', 'array'],
            // NOTE: once one nested rule exists, validated() drops un-ruled siblings —
            // every brand field the funnel uses must be listed here.
            'brand.logo'        => ['nullable', 'string', 'max:4000000'],
            'brand.companyName' => ['nullable', 'string', 'max:300'],
            'brand.name'        => ['nullable', 'string', 'max:300'],
            'brand.title'       => ['nullable', 'string', 'max:300'],
            'brand.email'       => ['nullable', 'string', 'max:300'],
            'brand.phone'       => ['nullable', 'string', 'max:300'],
            'brand.url'         => ['nullable', 'string', 'max:300'],
            'mode'              => ['nullable', 'string', 'max:20'],
            'quantityId'        => ['nullable', 'integer'],
            'optionValueIds'    => ['nullable', 'array'],
            'optionValueIds.*'  => ['integer'],
        ]);

        // Persist the big base64 blobs to disk and keep only URLs in the session —
        // otherwise every request drags megabytes through the DB session store.
        $data['preview'] = \App\Support\PreviewStore::persist($data['preview'] ?? null);
        if (isset($data['brand']['logo'])) {
            $data['brand']['logo'] = \App\Support\PreviewStore::persist($data['brand']['logo']);
        }

        // The work-in-progress design (fabric JSON) also goes to disk under its
        // project id: "Back to editor" resumes exactly this project (a clean
        // editor entry mints a new id and never sees other projects' work).
        if (! empty($data['design']) && ! empty($data['project'])) {
            $this->storeProject($product, $request, $data['project'], $data['design'], $data['preview']);
        }
        unset($data['design']); // session gets the id→path map only, never the blob

        // Register the design with the pqSmartGenerator upsell engine — async,
        // after this response is sent, so the shopper never waits on it.
        $data['pqsgKey'] = $this->dispatchPqsgCapture($data['brand'] ?? null, $data['preview'] ?? null);

        session(['design.review' => $data + ['product' => $product->slug]]);

        return redirect()->route('design.review', $product);
    }

    /** Debounced autosave from the editor — same storage as Review, so the
     *  design shows in "My designs" without ever reaching the Review step. */
    public function autosave(Product $product, Request $request)
    {
        abort_unless($product->is_active, 404);

        $data = $request->validate([
            'design'  => ['required', 'string', 'max:12000000'],
            'project' => ['required', 'uuid'],
            'preview' => ['nullable', 'string', 'max:1500000'], // small jpeg for the design card
        ]);

        $this->storeProject($product, $request, $data['project'], $data['design'],
            \App\Support\PreviewStore::persist($data['preview'] ?? null));

        return response()->noContent();
    }

    /** Store a project's fabric JSON on disk (one file per project), track it
     *  in the session map, and upsert the durable design_projects row. */
    private function storeProject(Product $product, Request $request, string $projectId, string $design, ?string $preview): void
    {
        $path = 'designs/'.now()->format('Ym').'/'.$projectId.'.json'; // re-save overwrites, no pile-up
        Storage::disk('public')->put($path, $design);
        $projects = session('design.projects', []);
        $projects[$projectId] = $path;
        session(['design.projects' => array_slice($projects, -10, null, true)]); // keep the last 10

        // The durable record behind "My designs" + cross-session edit links.
        // Owned by whoever is logged in; a later login claims guest projects.
        $project = \App\Models\DesignProject::firstOrNew(['id' => $projectId]);
        $project->fill([
            'product_slug' => $product->slug,
            'product_name' => $product->name,
            'preview'      => $preview ?: $project->preview,
            'design_path'  => $path,
        ]);
        $project->user_id ??= $request->user()?->id;
        $project->save();
    }

    public function showReview(Product $product, Pricing $pricing): Response|RedirectResponse
    {
        abort_unless($product->is_active, 404);

        $d = session('design.review');
        if (! $d || ($d['product'] ?? null) !== $product->slug) {
            return redirect()->route('design.start', $product);
        }

        $product->load('category');
        $quote = $pricing->quote($product, $d['quantityId'] ?? null, $d['optionValueIds'] ?? []);

        return Inertia::render('Review', [
            'product'  => $product->only('id', 'name', 'slug'),
            'category' => ['name' => $product->category->name, 'slug' => $product->category->slug],
            'preview'  => $d['preview'] ?? null,
            'mode'     => $d['mode'] ?? 'design',
            'design'   => [
                'brand'          => $d['brand'] ?? null,
                'quantityId'     => $d['quantityId'] ?? null,
                'optionValueIds' => $d['optionValueIds'] ?? [],
                'project'        => $d['project'] ?? null, // the back link resumes this project
            ],
            'quote'    => $quote,
        ]);
    }

    /**
     * Queue a pqSmartGenerator capture from the designer's brand fields (the logo
     * placeholder image and the company-website text). Seed placeholders are
     * skipped — the engine only gets real customer data. Returns our correlation
     * key (reused across upload + designer flows within the session), or null
     * when there is nothing worth sending.
     */
    private function dispatchPqsgCapture(?array $brand, ?string $preview = null): ?string
    {
        if (! config('shop.pqsg.enabled')) {
            return null;
        }

        $logo = $brand['logo'] ?? null;
        // the seeded "YOUR LOGO HERE" placeholder is not a customer logo
        if ($logo && str_contains($logo, 'logo-placeholder')) {
            $logo = null;
        }
        if ($logo && ! str_starts_with($logo, 'http')) {
            $logo = url($logo);
        }

        $website = trim((string) ($brand['url'] ?? ''));
        // the seeded placeholder URL is not a customer website
        if (preg_match('/^(www\.)?yourcompany\.com$/i', $website)) {
            $website = '';
        }
        if ($website !== '' && ! preg_match('#^https?://#i', $website)) {
            $website = 'https://'.$website;
        }

        // No real logo/website? The approved design itself carries the brand —
        // the engine extracts it from image_url (updated API), so placeholder
        // designs get the gallery too instead of silently skipping the steps.
        $image = $preview && str_starts_with($preview, '/') ? url($preview) : $preview;

        // Real brand data → always a FRESH capture. The key doubles as the
        // engine's idempotency key, so reusing the session key replays the
        // PREVIOUS capture — the funnel then shows a stale logo from an
        // earlier design (bit a real customer on 2026-07-06).
        if ($logo || $website !== '') {
            $key = (string) \Illuminate\Support\Str::uuid();
            session(['pqsg.key' => $key, 'pqsg.strong' => $key, 'pqsg.strong_at' => now()->toIso8601String()]);
            \App\Jobs\SendPqsgCapture::dispatchAfterResponse(
                key: $key,
                source: 'runmyprint-designer',
                logoUrl: $logo,
                website: $website !== '' ? $website : null,
                imageUrl: $logo ? null : $image, // the design preview is the fallback brand source
            );

            return $key;
        }

        // Placeholder design, but a strong capture from this session (uploaded
        // artwork, logo-maker download) already carries the real brand — hand
        // the funnel that one instead of clobbering it with the weak fallback.
        // ONLY if it still resolves though: the uuid cache lives 12h while a
        // session can live longer, and a dead key means both gallery steps
        // spin into the empty state (bit a WirMachenDruck test on 2026-07-07).
        // A just-dispatched capture may not be cached yet — grace-period it.
        if ($strong = session('pqsg.strong')) {
            $at = session('pqsg.strong_at');
            $inGrace = $at && \Illuminate\Support\Carbon::parse($at)
                ->gt(now()->subMinutes(max(0, (int) config('shop.pqsg.strong_grace'))));
            if ($inGrace || \Illuminate\Support\Facades\Cache::has("pqsg:{$strong}")) {
                session(['pqsg.key' => $strong]);

                return $strong;
            }
            session()->forget(['pqsg.strong', 'pqsg.strong_at']); // capture long gone — fall through to a fresh one
        }

        if ($image) {
            $key = (string) \Illuminate\Support\Str::uuid();
            session(['pqsg.key' => $key]);
            \App\Jobs\SendPqsgCapture::dispatchAfterResponse(
                key: $key,
                source: 'runmyprint-designer',
                imageUrl: $image,
            );

            return $key;
        }

        return session('pqsg.key'); // nothing to send; maybe an older capture exists
    }

    /** @return int[] */
    private function optionIds(Request $request): array
    {
        return array_values(array_filter(array_map('intval', (array) $request->query('opts', []))));
    }

    /** Templates for the product's category (business-card designs exist today). */
    private function templatesFor(Product $product): array
    {
        // Only the columns the picker needs — never the heavy `data` (embedded base64 images).
        return Template::where('is_active', true)
            ->where('category', $product->category->slug)
            ->orderByDesc('score')->orderBy('sort_order')
            ->take(60)->get(['ref', 'name', 'preview_path'])
            ->map(fn (Template $t) => ['ref' => $t->ref, 'name' => $t->name, 'preview' => $t->previewUrl()])
            ->all();
    }
}
