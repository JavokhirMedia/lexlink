<?php

namespace Tests\Feature\Auth\Concerns;

use App\Services\Gov\Dto\GovSubmission;
use App\Services\Gov\GovApiException;
use App\Services\Gov\GovDataProvider;

/**
 * Binds an in-memory GovDataProvider. Configure via $this->gov->oneIdUsers[code] = [...]
 * and $this->gov->licenses[number] = [...]; set $this->gov->down = true to simulate outages.
 */
trait FakesGovDataProvider
{
    protected object $gov;

    protected function fakeGov(): object
    {
        $this->gov = new class implements GovDataProvider
        {
            /** @var array<string, array<string, mixed>> */
            public array $oneIdUsers = [];

            /** @var array<string, array<string, mixed>> */
            public array $licenses = [];

            public bool $down = false;

            public function oneIdUser(string $code, string $redirectUri): array
            {
                if ($this->down || ! isset($this->oneIdUsers[$code])) {
                    throw new GovApiException('invalid_grant');
                }

                return $this->oneIdUsers[$code];
            }

            public function advocateLicense(string $licenseNumber): ?array
            {
                if ($this->down) {
                    throw new GovApiException('registry down');
                }

                return $this->licenses[$licenseNumber] ?? null;
            }

            public function courtDecision(string $number): ?array
            {
                return null;
            }

            public function courtCase(string $number): ?array
            {
                return null;
            }

            public function submit(GovSubmission $submission): array
            {
                throw new GovApiException('not faked');
            }

            public function status(string $requestId): array
            {
                throw new GovApiException('not faked');
            }

            public function result(string $requestId): array
            {
                throw new GovApiException('not faked');
            }

            public function advocateOrder(string $number): ?array
            {
                return null;
            }

            public function issueAdvocateOrder(array $data): array
            {
                throw new GovApiException('not faked');
            }
        };

        $this->app->instance(GovDataProvider::class, $this->gov);

        return $this->gov;
    }

    /** @return array<string, mixed> */
    protected function oneIdPerson(string $pinfl, string $name = 'Karimov Aziz Botirovich', ?string $sub = null): array
    {
        return [
            'sub' => $sub ?? 'oneid-'.$pinfl,
            'pinfl' => $pinfl,
            'full_name' => $name,
            'first_name' => 'Aziz',
            'last_name' => 'Karimov',
            'middle_name' => 'Botirovich',
            'birth_date' => '1985-04-12',
            'phone' => '+998901112233',
        ];
    }

    /** @return array<string, mixed> */
    protected function licenseRecord(string $number, string $pinfl, string $status = 'active'): array
    {
        return [
            'license_number' => $number,
            'full_name' => 'Karimov Aziz Botirovich',
            'pinfl' => $pinfl,
            'status' => $status,
            'bureau_name' => '"Adolat" advokatlik byurosi',
            'issued_at' => '2015-03-01',
        ];
    }
}
