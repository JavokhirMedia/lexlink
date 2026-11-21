<?php

namespace Tests\Feature\Cases;

use App\Enums\UserRole;
use App\Models\AdvocateLawyerLink;
use App\Models\LegalCase;
use App\Models\User;
use App\Services\Gov\Dto\GovSubmission;
use App\Services\Gov\GovDataProvider;
use Illuminate\Support\Str;

trait CasesTestHelpers
{
    /** @var array<string, array|null> */
    protected array $courtDecisions = [];

    protected ?string $licenseStatus = 'active';

    protected function fakeGov(): void
    {
        $test = $this;
        $this->app->instance(GovDataProvider::class, new class($test) implements GovDataProvider
        {
            public function __construct(private $test) {}

            public function oneIdUser(string $code, string $redirectUri): array
            {
                return [];
            }

            public function advocateLicense(string $licenseNumber): ?array
            {
                return ['license_number' => $licenseNumber, 'status' => $this->test->licenseStatusValue()];
            }

            public function courtDecision(string $number): ?array
            {
                return $this->test->courtDecisionFor($number);
            }

            public function courtCase(string $number): ?array
            {
                return null;
            }

            public function submit(GovSubmission $submission): array
            {
                return ['request_id' => 'x', 'status' => 'accepted'];
            }

            public function status(string $requestId): array
            {
                return ['request_id' => $requestId, 'status' => 'processing'];
            }

            public function result(string $requestId): array
            {
                return [];
            }

            public function advocateOrder(string $number): ?array
            {
                return $this->test->advocateOrderFor($number);
            }

            public function issueAdvocateOrder(array $data): array
            {
                return [];
            }
        });
    }

    /** @var array<string, array|null> e-order registry rows keyed by number */
    protected array $advocateOrders = [];

    public function advocateOrderFor(string $number): ?array
    {
        return $this->advocateOrders[$number] ?? null;
    }

    /** Register an active e-order of $advocate in the fake registry. */
    protected function order(string $number, User $advocate, array $override = []): array
    {
        return $this->advocateOrders[$number] = array_replace([
            'order_number' => $number,
            'license_number' => $advocate->advocateProfile->license_number,
            'advocate_name' => $advocate->name,
            'client' => ['pinfl' => '42303910261025', 'full_name' => 'Karimova Nilufar Shavkatovna'],
            'matter' => 'Aliment undirish',
            'issued_at' => now()->subDays(3)->toDateString(),
            'status' => 'active',
        ], $override);
    }

    public function courtDecisionFor(string $number): ?array
    {
        return $this->courtDecisions[$number] ?? null;
    }

    public function licenseStatusValue(): ?string
    {
        return $this->licenseStatus;
    }

    protected function advocate(string $licenseStatus = 'active'): User
    {
        $u = User::factory()->create(['role' => UserRole::Advocate]);
        $u->advocateProfile()->create(['license_number' => 'ADV-'.Str::random(6), 'license_status' => $licenseStatus, 'verified_at' => now()]);

        return $u->fresh();
    }

    protected function lawyer(): User
    {
        return User::factory()->create(['role' => UserRole::Lawyer]);
    }

    protected function link(User $advocate, User $lawyer, string $status = 'accepted'): AdvocateLawyerLink
    {
        return AdvocateLawyerLink::create([
            'advocate_id' => $advocate->id,
            'lawyer_id' => $lawyer->id,
            'invite_contact' => $lawyer->email,
            'token' => Str::random(40),
            'status' => $status,
            'invited_at' => now(),
            'accepted_at' => $status === 'accepted' ? now() : null,
        ]);
    }

    protected function caseFor(User $advocate, ?User $lawyer = null, array $lawyerPivot = []): LegalCase
    {
        $case = LegalCase::create([
            'owner_id' => $advocate->id,
            'title' => 'Aliment undirish',
            'client_name' => 'Karimova Dilnoza',
            'category' => 'aliment',
            'status' => 'active',
        ]);
        $case->memberships()->create(['user_id' => $advocate->id, 'role' => 'advocate', 'can_view_responses' => true]);
        if ($lawyer) {
            $case->memberships()->create(['user_id' => $lawyer->id, 'role' => 'lawyer', ...$lawyerPivot]);
        }

        return $case;
    }

    protected function decision(string $number, array $override = []): array
    {
        return array_replace([
            'number' => $number,
            'case_number' => '2-1234/2026',
            'court' => 'Toshkent shahar Yunusobod tumanlararo fuqarolik ishlari sudi',
            'judge' => 'Sudya A.',
            'decision_type' => 'ajrim',
            'subject' => ['pinfl' => '31234567890112', 'full_name' => 'Karimov Aziz'],
            'allowed_types' => ['salary', 'property'],
            'issued_at' => now()->subDays(10)->toDateString(),
            'expires_at' => now()->addMonths(3)->toDateString(),
            'summary' => 'Daromad ma\'lumotlarini olishga ruxsat',
        ], $override);
    }
}
