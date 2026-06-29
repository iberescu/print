<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DesignController extends Controller
{
    public function show(Product $product, Request $request): Response
    {
        abort_unless($product->is_active, 404);
        $product->load('category');

        return Inertia::render('Editor', [
            'product'  => $product->only('id', 'name', 'slug'),
            'category' => ['name' => $product->category->name, 'slug' => $product->category->slug],
            'mode'     => $request->query('mode') === 'upload' ? 'upload' : 'design',
        ]);
    }

    public function store(Product $product, Request $request): RedirectResponse
    {
        $data = $request->validate([
            'design'  => ['required', 'array'],
            'preview' => ['nullable', 'string'],
        ]);

        $request->session()->put('pending_design', [
            'product' => $product->only('id', 'name', 'slug'),
            'design'  => $data['design'],
            'preview' => $data['preview'] ?? null,
        ]);

        return redirect()->route('cart')->with('success', "“{$product->name}” design saved to your cart.");
    }
}
