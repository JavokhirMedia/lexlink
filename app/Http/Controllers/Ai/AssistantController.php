<?php

namespace App\Http\Controllers\Ai;

use App\Http\Controllers\Controller;
use App\Models\LawDocument;
use App\Services\Ai\AiService;
use App\Services\Ai\GeminiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssistantController extends Controller
{
    public function show(GeminiClient $gemini): Response
    {
        return Inertia::render('ai/assistant', [
            'laws' => LawDocument::query()->withCount('chunks')->orderBy('id')->get()
                ->map(fn (LawDocument $d) => ['slug' => $d->slug, 'title' => $d->title, 'short_title' => $d->short_title, 'url' => $d->lex_url, 'chunks' => $d->chunks_count])
                ->values(),
            'ai_enabled' => $gemini->enabled(),
            'examples' => [
                'Bir bola uchun aliment miqdori qancha?',
                "Advokat so'roviga qancha muddatda javob berilishi kerak?",
                'Soliq siri qaysi hollarda oshkor qilinishi mumkin?',
                "Shaxsga doir ma'lumotlarga ishlov berish qanday hollarda ruxsat etiladi?",
            ],
        ]);
    }

    public function ask(Request $request, AiService $ai): JsonResponse
    {
        $data = $request->validate(['question' => ['required', 'string', 'min:3', 'max:2000']]);

        return response()->json($ai->askLaw($data['question']));
    }
}
