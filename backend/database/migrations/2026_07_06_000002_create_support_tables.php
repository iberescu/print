<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Support chat (req: Zendesk-style bubble + admin inbox + AI auto-answer).
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->index();          // anonymous visitor identity (session-bound)
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // open → ai (auto-answered) | needs_human (AI punted — red) | answered (human replied) | closed
            $table->string('status', 20)->default('open');
            $table->timestamps();
        });

        Schema::create('support_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_ticket_id')->constrained()->cascadeOnDelete();
            $table->string('sender', 12);                  // customer | ai | admin
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_messages');
        Schema::dropIfExists('support_tickets');
    }
};
