<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Advocate-order path:
 *  - data_requests.due_at / deadline_basis — Advokatura to'g'risidagi Qonun 7¹-modda (15 kun / 3 ish kuni)
 *  - legal_bases.purpose — "so'rov maqsadi" linking an advocate order to the order matter
 *  - case_client_links — client (ishonch bildiruvchi) read-only access to a case via the advocate order
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('data_requests', function (Blueprint $table) {
            $table->timestamp('due_at')->nullable()->after('submitted_at');
            $table->string('deadline_basis', 24)->nullable()->after('due_at'); // default|proceedings|court_decision
        });

        Schema::table('legal_bases', function (Blueprint $table) {
            $table->string('purpose', 500)->nullable()->after('subject_name');
        });

        Schema::create('case_client_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->string('order_number', 32);
            $table->string('client_pinfl_hash', 64)->index();
            $table->string('client_name')->nullable();
            $table->string('token', 64)->unique();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('client_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('granted_at')->nullable();
            $table->timestamp('last_viewed_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_client_links');
        Schema::table('legal_bases', fn (Blueprint $table) => $table->dropColumn('purpose'));
        Schema::table('data_requests', fn (Blueprint $table) => $table->dropColumn(['due_at', 'deadline_basis']));
    }
};
