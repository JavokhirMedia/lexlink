<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fictional registries for the domain types (LegalMatrix): tax_info, business_shares, bank_accounts, border_crossing.
 * Mock-gov only — the main app reaches them over HTTP through MockGovProvider. All data is invented.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mock_tax_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('pinfl', 14)->unique();
            $table->string('tin', 9);
            $table->string('tax_regime'); // Jismoniy shaxs (JShDS) | Aylanmadan olinadigan soliq | Qat'iy belgilangan soliq ...
            $table->boolean('vat_payer')->default(false);
            $table->boolean('is_individual_entrepreneur')->default(false);
            $table->string('ie_activity')->nullable();
            $table->unsignedBigInteger('tax_debt')->default(0);
            $table->unsignedBigInteger('taxes_paid_last_year')->default(0);
            $table->date('registered_at');
            $table->timestamps();
        });

        Schema::create('mock_business_shares', function (Blueprint $table) {
            $table->id();
            $table->string('pinfl', 14)->index();
            $table->string('company_name');
            $table->string('company_tin', 9);
            $table->decimal('share_percent', 5, 2);
            $table->unsignedBigInteger('charter_capital');
            $table->date('since');
            $table->string('company_status'); // Faol | Tugatish jarayonida | Faoliyati to'xtatilgan
            $table->timestamps();
        });

        Schema::create('mock_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('pinfl', 14)->index();
            $table->string('bank_name');
            $table->string('account_mask', 32);
            $table->string('currency', 3); // UZS | USD | EUR
            $table->bigInteger('balance'); // minor units NOT used: whole so'm / whole USD
            $table->unsignedBigInteger('turnover_in')->default(0);  // last 6 months
            $table->unsignedBigInteger('turnover_out')->default(0); // last 6 months
            $table->date('opened_at');
            $table->json('restrictions')->nullable(); // [{type, by, since}]
            $table->timestamps();
        });

        Schema::create('mock_border_crossings', function (Blueprint $table) {
            $table->id();
            $table->string('pinfl', 14)->index();
            $table->string('direction', 8); // exit | entry
            $table->dateTime('crossed_at');
            $table->string('country');
            $table->string('checkpoint');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['mock_border_crossings', 'mock_bank_accounts', 'mock_business_shares', 'mock_tax_profiles'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
