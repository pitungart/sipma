<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Salinan SKS saat nilai disimpan: IP mahasiswa yang sudah dinilai tidak berubah bila SKS di
     * paket diubah kemudian.
     */
    public function up(): void
    {
        Schema::table('student_courses', function (Blueprint $table) {
            $table->unsignedTinyInteger('graded_credits')->nullable()->after('grade_point');
        });
    }

    public function down(): void
    {
        Schema::table('student_courses', function (Blueprint $table) {
            $table->dropColumn('graded_credits');
        });
    }
};
