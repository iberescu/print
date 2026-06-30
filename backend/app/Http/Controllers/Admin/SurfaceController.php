<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Surface;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SurfaceController extends Controller
{
    public function index()
    {
        $surfaces = Surface::orderBy('name')->get()->map(fn (Surface $s) => [
            'slug'    => $s->slug,
            'name'    => $s->name,
            'unit'    => $s->unit,
            'size'    => rtrim(rtrim((string) $s->width, '0'), '.').' × '.rtrim(rtrim((string) $s->height, '0'), '.').' '.$s->unit,
            'bleed'   => (float) $s->bleed,
            'safety'  => (float) $s->safety,
            'noPrint' => count($s->no_print_areas ?? []),
            'fold'    => count($s->fold_lines ?? []),
            'active'  => (bool) $s->is_active,
        ]);

        return Inertia::render('Admin/Surfaces/Index', ['surfaces' => $surfaces]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);

        $surface = Surface::create([
            'name'           => $data['name'],
            'slug'           => $this->uniqueSlug($data['name']),
            'unit'           => 'mm',
            'width'          => 90, 'height' => 50, 'bleed' => 3, 'safety' => 3,
            'no_print_areas' => [], 'fold_lines' => [], 'is_active' => true,
        ]);

        return redirect()->route('admin.surfaces.edit', $surface)->with('success', 'Surface created — set its dimensions.');
    }

    public function edit(Surface $surface)
    {
        return Inertia::render('Admin/Surfaces/Edit', [
            'surface' => [
                'slug'     => $surface->slug,
                'name'     => $surface->name,
                'unit'     => $surface->unit,
                'width'    => (float) $surface->width,
                'height'   => (float) $surface->height,
                'bleed'    => (float) $surface->bleed,
                'safety'   => (float) $surface->safety,
                'noPrint'  => $surface->no_print_areas ?? [],
                'fold'     => $surface->fold_lines ?? [],
                'isActive' => (bool) $surface->is_active,
            ],
        ]);
    }

    public function update(Request $request, Surface $surface)
    {
        $data = $request->validate([
            'name'                => ['required', 'string', 'max:120'],
            'slug'                => ['required', 'string', 'max:120', Rule::unique('surfaces', 'slug')->ignore($surface->id)],
            'unit'                => ['required', 'in:mm,in'],
            'width'               => ['required', 'numeric', 'min:1'],
            'height'              => ['required', 'numeric', 'min:1'],
            'bleed'               => ['nullable', 'numeric', 'min:0'],
            'safety'              => ['nullable', 'numeric', 'min:0'],
            'isActive'            => ['boolean'],
            'noPrint'             => ['array'],
            'noPrint.*.label'     => ['nullable', 'string', 'max:60'],
            'noPrint.*.x'         => ['nullable', 'numeric', 'min:0'],
            'noPrint.*.y'         => ['nullable', 'numeric', 'min:0'],
            'noPrint.*.w'         => ['nullable', 'numeric', 'min:0'],
            'noPrint.*.h'         => ['nullable', 'numeric', 'min:0'],
            'fold'                => ['array'],
            'fold.*.label'        => ['nullable', 'string', 'max:60'],
            'fold.*.orientation'  => ['required_with:fold.*.position', 'in:vertical,horizontal'],
            'fold.*.position'     => ['nullable', 'numeric', 'min:0'],
        ]);

        $surface->update([
            'name'           => $data['name'],
            'slug'           => $data['slug'],
            'unit'           => $data['unit'],
            'width'          => $data['width'],
            'height'         => $data['height'],
            'bleed'          => $data['bleed'] ?? 0,
            'safety'         => $data['safety'] ?? 0,
            'is_active'      => $data['isActive'] ?? true,
            'no_print_areas' => array_values($data['noPrint'] ?? []),
            'fold_lines'     => array_values($data['fold'] ?? []),
        ]);

        return redirect()->route('admin.surfaces.edit', $surface)->with('success', 'Surface saved.');
    }

    public function destroy(Surface $surface)
    {
        $surface->delete(); // products/values fall back to auto sizing (nullOnDelete)

        return redirect()->route('admin.surfaces.index')->with('success', 'Surface deleted.');
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'surface';
        $slug = $base;
        $i = 2;
        while (Surface::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
