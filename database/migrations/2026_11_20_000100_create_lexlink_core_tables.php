<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LexLink core domain tables. PII columns marked "encrypted" use Laravel's
 * `encrypted` cast and are stored as TEXT; *_hash columns hold sha256(pinfl)
 * for equality lookups, *_masked hold display-safe values (3*********12).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advocate_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('license_number', 32)->index();
            $table->string('license_status', 16)->default('pending'); // active|revoked|suspended|pending
            $table->string('bureau_name')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('tin', 16)->nullable();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('organization_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 16)->default('member'); // owner|member
            $table->timestamps();
            $table->unique(['organization_id', 'user_id']);
        });

        Schema::create('advocate_lawyer_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('advocate_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('lawyer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('invite_contact'); // email or phone the advocate typed
            $table->string('token', 64)->unique();
            $table->string('status', 16)->default('pending'); // pending|accepted|revoked
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete(); // advocate (or lawyer who created draft)
            $table->string('title');
            $table->string('court_case_number', 64)->nullable()->index();
            $table->string('client_name');
            $table->string('category', 32)->nullable(); // alimony|labor|property|civil|criminal|other
            $table->text('description')->nullable();
            $table->json('parties')->nullable(); // [{role, name}]
            $table->string('status', 16)->default('active'); // active|closed
            $table->timestamps();
        });

        Schema::create('case_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 16)->default('lawyer'); // advocate|lawyer
            $table->boolean('can_view_responses')->default(false);
            $table->timestamp('secrecy_declared_at')->nullable(); // lawyer accepted "advokat siri" declaration for this case
            $table->timestamps();
            $table->unique(['case_id', 'user_id']);
        });

        Schema::create('legal_bases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->string('type', 24); // court_decision|advocate_order
            $table->string('number', 64)->index();
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->text('subject_pinfl')->nullable(); // encrypted
            $table->string('subject_pinfl_hash', 64)->nullable()->index();
            $table->string('subject_pinfl_masked', 16)->nullable();
            $table->string('subject_name')->nullable();
            $table->string('court_name')->nullable();
            $table->json('allowed_types')->nullable(); // ["salary","property",...]
            $table->string('file_path')->nullable();
            $table->string('verification_status', 16)->default('pending'); // pending|verified|not_found|expired|mismatch
            $table->json('verification_payload')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('data_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->foreignId('legal_basis_id')->nullable()->constrained('legal_bases')->nullOnDelete();
            $table->string('type', 16); // court_case|salary|property|vehicle
            $table->text('subject_pinfl')->nullable(); // encrypted
            $table->string('subject_pinfl_hash', 64)->nullable()->index();
            $table->string('subject_pinfl_masked', 16)->nullable();
            $table->string('subject_name')->nullable();
            $table->json('params')->nullable(); // {period_from, period_to, cadastral_number, court_case_number}
            $table->text('purpose')->nullable();
            $table->longText('request_text')->nullable(); // formal request letter (AI-assisted)
            $table->string('status', 24)->default('draft')->index(); // see App\Enums\DataRequestStatus
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('signed_by')->nullable()->constrained('users');
            $table->timestamp('signed_at')->nullable();
            $table->string('signature')->nullable(); // mock ERI signature hash
            $table->string('gov_request_id', 64)->nullable()->index();
            $table->unsignedInteger('cost')->default(0); // so'm
            $table->boolean('charged_from_subscription')->default(false);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('request_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('data_request_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('summary')->nullable(); // non-PII short summary, kept after purge
            $table->longText('payload')->nullable(); // encrypted JSON, purged after retention
            $table->string('pdf_path')->nullable();
            $table->string('gov_signature')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('purged_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('data_request_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('case_id')->nullable()->constrained('cases')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind', 24); // request_text|summary|draft_claim
            $table->string('status', 16)->default('completed'); // pending|completed|failed
            $table->string('model', 64)->nullable();
            $table->string('prompt_version', 16)->default('v1');
            $table->longText('output')->nullable(); // markdown, PII re-inserted only at render time if allowed
            $table->json('structured')->nullable(); // e.g. {average_monthly, alimony:{children, share, amount}}
            $table->json('citations')->nullable(); // [{law, article, title, url}]
            $table->unsignedInteger('tokens')->default(0);
            $table->unsignedInteger('cost')->default(0); // so'm charged
            $table->timestamps();
        });

        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('code', 16)->unique(); // start|advocate|firm
            $table->string('name');
            $table->string('audience');
            $table->unsignedInteger('price'); // so'm / month
            $table->unsignedInteger('included_requests');
            $table->unsignedInteger('extra_price')->nullable(); // per extra request; null = not allowed
            $table->unsignedInteger('included_ai')->default(0);
            $table->json('features')->nullable();
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained();
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedInteger('used_requests')->default(0);
            $table->unsignedInteger('used_ai')->default(0);
            $table->timestamps();
        });

        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->bigInteger('balance')->default(0); // so'm
            $table->timestamps();
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16); // topup|charge|refund|subscription|ai
            $table->bigInteger('amount'); // positive = credit, negative = debit
            $table->bigInteger('balance_after');
            $table->string('provider', 16)->nullable(); // click|payme
            $table->foreignId('data_request_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 16)->default('completed'); // pending|completed|failed
            $table->string('external_id', 64)->nullable();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 48)->index(); // see App\Enums\AuditAction
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->foreignId('case_id')->nullable()->constrained('cases')->nullOnDelete();
            $table->json('meta')->nullable();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index(['subject_type', 'subject_id']);
        });

        // lex.uz knowledge base for RAG
        Schema::create('law_documents', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('title');
            $table->string('short_title', 128);
            $table->string('lex_id', 32)->nullable();
            $table->string('lex_url')->nullable();
            $table->date('adopted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('law_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('law_document_id')->constrained()->cascadeOnDelete();
            $table->string('article_number', 16)->nullable()->index();
            $table->string('article_title', 512)->nullable();
            $table->longText('content');
            $table->longText('embedding')->nullable(); // JSON float array
            $table->unsignedInteger('token_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['law_chunks', 'law_documents', 'audit_logs', 'transactions', 'wallets', 'subscriptions', 'plans', 'ai_analyses', 'request_responses', 'data_requests', 'legal_bases', 'case_members', 'cases', 'advocate_lawyer_links', 'organization_members', 'organizations', 'advocate_profiles'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
