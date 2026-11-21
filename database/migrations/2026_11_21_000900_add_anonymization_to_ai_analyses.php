<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ai module: transparency of what was sent to Gemini.
 *  sent_payload          — the exact egasizlantirilgan prompt text that left the server (null = AI not called)
 *  anonymization_report  — categories + counts replaced, dropped fields, leak_check, legal basis (never values)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_analyses', function (Blueprint $table) {
            $table->longText('sent_payload')->nullable()->after('output');
            $table->json('anonymization_report')->nullable()->after('sent_payload');
        });
    }

    public function down(): void
    {
        Schema::table('ai_analyses', function (Blueprint $table) {
            $table->dropColumn(['sent_payload', 'anonymization_report']);
        });
    }
};
