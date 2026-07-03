<?php

namespace App\Http\Controllers;

use App\Jobs\SendPqsgCapture;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * pqSmartGenerator integration endpoints.
 *
 *  - POST /pqsg/upload: fire-and-forget artwork upload from the editor's upload
 *    mode — stores the file and registers a capture (pdf_url / logo_url) with
 *    the upsell engine after the response is sent. Never blocks the editor.
 *  - GET /pqsg/status/{key}: the Review page polls this until the third-party
 *    capture UUID is known, then hands it to the gallery widget.
 */
class PqsgController extends Controller
{
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,png,jpg,jpeg,webp', 'max:20480'], // 20 MB
        ]);

        $file = $request->file('file');
        $isPdf = strtolower($file->getClientOriginalExtension()) === 'pdf';
        $path = $file->store('uploads/artwork/'.now()->format('Ym'), 'public');
        $url = url(\Illuminate\Support\Facades\Storage::disk('public')->url($path));

        // one capture key per designer session; reused at Review so both flows share it
        $key = session('pqsg.key') ?? (string) Str::uuid();
        session(['pqsg.key' => $key]);

        SendPqsgCapture::dispatchAfterResponse(
            key: $key,
            source: 'runmyprint-upload',
            logoUrl: $isPdf ? null : $url,
            pdfUrl: $isPdf ? $url : null,
        );

        return response()->json(['key' => $key]);
    }

    public function status(string $key): JsonResponse
    {
        abort_unless(Str::isUuid($key), 404);

        return response()->json(['uuid' => Cache::get("pqsg:{$key}")]);
    }
}
