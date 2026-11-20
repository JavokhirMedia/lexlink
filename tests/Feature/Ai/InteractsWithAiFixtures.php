<?php

namespace Tests\Feature\Ai;

use App\Enums\DataRequestStatus;
use App\Enums\DataRequestType;
use App\Enums\UserRole;
use App\Models\AdvocateProfile;
use App\Models\CaseMember;
use App\Models\DataRequest;
use App\Models\LegalBasis;
use App\Models\LegalCase;
use App\Models\RequestResponse;
use App\Models\User;
use App\Services\Ai\Lex\LexCatalog;
use App\Services\Ai\Lex\LexSnapshotLoader;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

/** Shared fixtures (all persons and numbers are fictional). */
trait InteractsWithAiFixtures
{
    protected const SUBJECT_PINFL = '31507901234567';

    protected const SUBJECT_NAME = 'Qodirov Jasur Anvarovich';

    protected const CLIENT_NAME = 'Qodirova Malika Rustamovna';

    protected const ADVOCATE_NAME = 'Saidov Bekzod Olimovich';

    /** Loads the committed lex.uz snapshot (only the laws the AI pipeline cites). */
    protected function loadLaws(array $slugs = ['shaxsga-doir-malumotlar', 'advokatura', 'advokatlik-kafolatlari', 'oila-kodeksi', 'soliq-kodeksi', 'fuqarolik-protsessual-kodeksi', 'mehnat-kodeksi']): void
    {
        $snapshot = json_decode(File::get(LexCatalog::snapshotPath()), true);
        $snapshot['documents'] = array_values(array_filter($snapshot['documents'], fn ($d) => in_array($d['slug'], $slugs, true)));
        app(LexSnapshotLoader::class)->load($snapshot);
    }

    protected function advocate(): User
    {
        $user = User::factory()->create(['name' => self::ADVOCATE_NAME, 'role' => UserRole::Advocate]);
        AdvocateProfile::create(['user_id' => $user->id, 'license_number' => 'AL-00777', 'license_status' => 'active', 'verified_at' => now()]);

        return $user;
    }

    protected function alimonyCase(User $advocate, array $attrs = []): LegalCase
    {
        $case = LegalCase::create(array_merge([
            'owner_id' => $advocate->id,
            'title' => 'Aliment undirish — Qodirova M.',
            'court_case_number' => '2-1001-2026/15',
            'client_name' => self::CLIENT_NAME,
            'category' => 'aliment',
            'description' => 'Voyaga yetmagan 2 nafar farzand taʼminoti uchun aliment. Javobgar: '.self::SUBJECT_NAME.', tel +998 90 111-22-33.',
            'parties' => [['role' => 'Javobgar', 'name' => 'Qodirov J. A.']],
            'status' => 'active',
        ], $attrs));
        CaseMember::create(['case_id' => $case->id, 'user_id' => $advocate->id, 'role' => 'advocate', 'can_view_responses' => true, 'secrecy_declared_at' => now()]);

        return $case;
    }

    protected function completedRequest(LegalCase $case, User $advocate, DataRequestType $type = DataRequestType::Salary, ?array $data = null): DataRequest
    {
        $basis = LegalBasis::create([
            'case_id' => $case->id, 'created_by' => $advocate->id, 'type' => 'court_decision', 'number' => '2-1001-2026/15-A',
            'issued_at' => now()->subDays(10), 'expires_at' => now()->addMonths(3), 'subject_pinfl' => self::SUBJECT_PINFL,
            'subject_name' => self::SUBJECT_NAME, 'court_name' => 'Yunusobod tumanlararo sudi', 'allowed_types' => ['salary', 'property', 'vehicle', 'court_case'],
            'verification_status' => 'verified', 'verified_at' => now(),
        ]);

        $request = DataRequest::create([
            'case_id' => $case->id, 'legal_basis_id' => $basis->id, 'type' => $type, 'subject_pinfl' => self::SUBJECT_PINFL,
            'subject_name' => self::SUBJECT_NAME, 'params' => ['period_from' => '2026-01', 'period_to' => '2026-06'],
            'purpose' => 'Aliment miqdorini aniqlash', 'status' => DataRequestStatus::Completed, 'created_by' => $advocate->id,
            'signed_by' => $advocate->id, 'signed_at' => now(), 'submitted_at' => now(), 'completed_at' => now(),
        ]);

        RequestResponse::create([
            'data_request_id' => $request->id,
            'payload' => [
                'type' => $type->value,
                'subject' => ['pinfl' => '315*********67', 'full_name' => 'Qodirov J.'],
                'data' => $data ?? $this->salaryData(),
            ],
            'summary' => ['note' => 'test'],
            'received_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);

        return $request->fresh(['response', 'legalCase', 'legalBasis']);
    }

    protected function salaryData(): array
    {
        $months = [];
        foreach (['2026-01' => 4_000_000, '2026-02' => 4_000_000, '2026-03' => 4_200_000, '2026-04' => 4_200_000, '2026-05' => 4_400_000, '2026-06' => 3_200_000] as $period => $amount) {
            $months[] = ['period' => $period, 'employer' => '"Toshkent Tekstil" MChJ', 'amount' => $amount, 'pension_contribution' => (int) ($amount * 0.001)];
        }
        $months[] = ['period' => '2026-06', 'employer' => '"Yangi Yoʻl Savdo" MChJ', 'amount' => 1_000_000, 'pension_contribution' => 1000];

        return [
            'employers' => [['name' => '"Toshkent Tekstil" MChJ', 'tin' => '301234567'], ['name' => '"Yangi Yoʻl Savdo" MChJ', 'tin' => '302345678']],
            'months' => $months,
            'total' => 25_000_000,
            'average' => 4_166_667,
            'period' => ['from' => '2026-01', 'to' => '2026-06'],
        ];
    }

    protected function fakeGemini(string $text = 'Tahlil: [SHAXS_1] daromadi barqaror [1].', int $tokens = 321): void
    {
        config(['lexlink.ai.gemini_key' => 'test-key', 'lexlink.ai.gemini_model' => 'gemini-test-flash']);
        Http::fake([
            '*:generateContent' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => $text]]], 'finishReason' => 'STOP']],
                'usageMetadata' => ['totalTokenCount' => $tokens],
                'modelVersion' => 'gemini-test-flash',
            ]),
            '*:batchEmbedContents' => Http::response(['embeddings' => [['values' => [1, 0, 0]]]]),
        ]);
    }
}
