<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fictional "government" registries behind /mock-gov/v1/*. All data is invented.
 * These tables are ONLY touched by App\Http\Controllers\MockGov\* — the main app
 * reaches them exclusively over HTTP through App\Services\Gov\MockGovProvider.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mock_persons', function (Blueprint $table) {
            $table->id();
            $table->string('pinfl', 14)->unique();
            $table->string('last_name');
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->date('birth_date');
            $table->string('passport', 16)->nullable();
            $table->string('address')->nullable();
            $table->string('phone', 32)->nullable();
            $table->timestamps();
        });

        Schema::create('mock_advocate_licenses', function (Blueprint $table) {
            $table->id();
            $table->string('license_number', 32)->unique();
            $table->string('pinfl', 14)->index();
            $table->string('full_name');
            $table->string('status', 16); // active|revoked|suspended
            $table->string('bureau_name')->nullable();
            $table->date('issued_at');
            $table->date('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('mock_court_cases', function (Blueprint $table) {
            $table->id();
            $table->string('case_number', 64)->unique();
            $table->string('court');
            $table->string('judge');
            $table->string('category');
            $table->string('status'); // Ish yuritishda|Ko'rib chiqilmoqda|Hal qilingan|...
            $table->json('parties'); // [{role, name, pinfl}]
            $table->json('hearings'); // [{date, time, room, note}]
            $table->json('decisions'); // [{number, date, type, summary}]
            $table->timestamps();
        });

        Schema::create('mock_court_decisions', function (Blueprint $table) {
            $table->id();
            $table->string('number', 64)->unique();
            $table->string('case_number', 64)->index();
            $table->string('court');
            $table->string('judge');
            $table->string('decision_type'); // ajrim|hal qiluv qarori
            $table->string('subject_pinfl', 14)->index();
            $table->string('subject_name');
            $table->json('allowed_types'); // ["salary","property","vehicle","court_case"]
            $table->string('requester_license', 32)->nullable(); // advocate the decision authorises (optional)
            $table->date('issued_at');
            $table->date('expires_at');
            $table->text('summary')->nullable();
            $table->timestamps();
        });

        Schema::create('mock_salaries', function (Blueprint $table) {
            $table->id();
            $table->string('pinfl', 14)->index();
            $table->string('employer_name');
            $table->string('employer_tin', 9);
            $table->string('period', 7); // YYYY-MM
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('pension_contribution')->default(0);
            $table->timestamps();
        });

        Schema::create('mock_properties', function (Blueprint $table) {
            $table->id();
            $table->string('pinfl', 14)->index();
            $table->string('cadastral_number', 32)->index();
            $table->string('type'); // Kvartira|Uy-joy|Yer uchastkasi|Noturar bino
            $table->string('address');
            $table->decimal('area', 10, 2);
            $table->string('share')->default('1/1');
            $table->date('registered_at');
            $table->json('encumbrances')->nullable(); // [{type, holder, since}]
            $table->timestamps();
        });

        Schema::create('mock_vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('pinfl', 14)->index();
            $table->string('model');
            $table->unsignedSmallInteger('year');
            $table->string('plate', 16);
            $table->string('color')->nullable();
            $table->string('vin', 17)->nullable();
            $table->date('registered_at');
            $table->json('pledge')->nullable(); // {holder, since} or null
            $table->timestamps();
        });

        Schema::create('mock_gov_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_id', 32)->unique(); // GOV-2026-000123
            $table->string('type', 16);
            $table->string('subject_pinfl', 14);
            $table->json('legal_basis'); // {type, number}
            $table->json('period')->nullable();
            $table->json('params')->nullable();
            $table->string('requester_license', 32)->nullable();
            $table->string('status', 16)->default('accepted'); // accepted|processing|completed|rejected
            $table->timestamp('ready_at'); // now + 2..8s
            $table->json('result')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('mock_oneid_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('pinfl', 14);
            $table->string('redirect_uri');
            $table->string('access_token', 64)->nullable()->unique();
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['mock_oneid_codes', 'mock_gov_requests', 'mock_vehicles', 'mock_properties', 'mock_salaries', 'mock_court_decisions', 'mock_court_cases', 'mock_advocate_licenses', 'mock_persons'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
