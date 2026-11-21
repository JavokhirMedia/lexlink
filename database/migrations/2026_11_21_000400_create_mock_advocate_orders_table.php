<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fictional "Advokatlar palatasi — elektron orderlar reyestri" behind /mock-gov/v1/advocate-orders.
 * Only App\Http\Controllers\MockGov\* touches it; the app reads it over HTTP via GovDataProvider.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mock_advocate_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 32)->unique();   // ORD-2026-000457
            $table->string('license_number', 32)->index();  // issuing advocate
            $table->string('advocate_name');
            $table->string('client_pinfl', 14)->index();
            $table->string('client_name');
            $table->string('matter');                        // ish mazmuni
            $table->date('issued_at');
            $table->string('status', 16)->default('active'); // active|cancelled
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mock_advocate_orders');
    }
};
