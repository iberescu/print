<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surfaces', function (Blueprint $table) {
            // Die-cut / sewn edge as an SVG path in NORMALIZED coordinates (0–100 on
            // both axes, relative to the trim box). Rendered by the editor in place of
            // the rectangular trim line — feather flags, circle/oval cards, die-cuts.
            $table->text('cut_path')->nullable()->after('fold_lines');
        });

        Schema::table('products', function (Blueprint $table) {
            // How the design is applied: 'print' (default) or 'embroidery' (stitched —
            // no bleed, limited colours; the editor shows stitch guidance).
            $table->string('decoration', 20)->default('print')->after('supports_upload');
        });
    }

    public function down(): void
    {
        Schema::table('surfaces', fn (Blueprint $table) => $table->dropColumn('cut_path'));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('decoration'));
    }
};
