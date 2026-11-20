<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** ai module: lex.uz article anchors + content hash (re-embedding only when text changed). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('law_chunks', function (Blueprint $table) {
            $table->string('anchor', 32)->nullable()->after('article_title'); // lex.uz element id, e.g. "-4013524"
            $table->string('chapter', 512)->nullable()->after('anchor');
            $table->unsignedSmallInteger('part')->default(1)->after('chapter'); // long articles are split into parts
            $table->string('content_hash', 64)->nullable()->index()->after('content');
        });
    }

    public function down(): void
    {
        Schema::table('law_chunks', function (Blueprint $table) {
            $table->dropColumn(['anchor', 'chapter', 'part', 'content_hash']);
        });
    }
};
