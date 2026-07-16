<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            // Email-channel tickets: inbound mail to contact@ lands in the same
            // inbox as the chat bubble; replies go back out by email.
            $table->string('channel')->default('chat')->after('token');
            $table->string('email')->nullable()->after('channel');
            $table->string('subject')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropColumn(['channel', 'email', 'subject']);
        });
    }
};
