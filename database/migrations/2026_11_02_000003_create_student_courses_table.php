<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mata kuliah yang ditempuh mahasiswa (TP-3). MK yang dipakai mahasiswa tidak bisa dihapus
     * permanen; kolom nilai diisi di F3.
     */
    public function up(): void
    {
        Schema::create('student_courses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('student_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('course_id')->constrained()->restrictOnDelete();
            $table->string('status');
            $table->string('grade')->nullable();
            $table->decimal('grade_point', 3, 2)->nullable();
            $table->timestamp('graded_at')->nullable();
            $table->foreignUuid('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'course_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_courses');
    }
};
