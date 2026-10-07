<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Periode (intake) pendaftaran sebuah program: kapan pendaftaran dibuka/ditutup dan kuotanya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_periods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('program_id')->constrained()->cascadeOnDelete();
            $table->string('name'); // mis. "Summer 2026"
            $table->string('code', 30)->unique(); // mis. SUMMER-2026
            $table->date('registration_opens_at');
            $table->date('registration_closes_at');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->unsignedInteger('quota')->nullable(); // null = tanpa batas
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_periods');
    }
};
