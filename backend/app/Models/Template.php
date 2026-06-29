<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Template extends Model
{
    protected $guarded = [];

    protected $casts = [
        'data'      => 'array',
        'score'     => 'decimal:1',
        'is_active' => 'boolean',
    ];

    public function getRouteKeyName(): string
    {
        return 'ref';
    }

    public function previewUrl(): ?string
    {
        return $this->preview_path && Storage::disk('public')->exists($this->preview_path)
            ? Storage::disk('public')->url($this->preview_path)
            : null;
    }
}
