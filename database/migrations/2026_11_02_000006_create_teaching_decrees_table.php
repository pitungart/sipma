<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SK Mengajar (TP-4): satu SK menugaskan beberapa dosen untuk beberapa MK. Catatan administrasi,
     * bukan dasar hak mengisi nilai (K3). Nomor SK berasal dari kantor Dekan/Rektor, jadi diisi manual.
     */
    public function up(): void
    {
        Schema::create('teaching_decrees', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('faculty_id')->constrained()->restrictOnDelete();
            $table->string('decree_number', 100);
            $table->date('decree_date');
            $table->string('document')->nullable();
            $table->date('period_start');
            $table->date('period_end');
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['faculty_id', 'decree_number']);
        });

        Schema::create('teaching_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('teaching_decree_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('lecturer_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('course_id')->constrained()->restrictOnDelete();
            $table->string('role');
            $table->timestamps();

            // Nama pendek: nama otomatisnya melebihi batas 64 karakter MySQL
            $table->unique(['teaching_decree_id', 'lecturer_id', 'course_id'], 'teaching_assignments_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teaching_assignments');
        Schema::dropIfExists('teaching_decrees');
    }
};
