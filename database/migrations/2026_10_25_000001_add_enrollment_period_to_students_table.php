<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bulan & tahun masuk pengganti periode akademik (wawancara KUI, 25 Oktober 2026): untuk pelaporan
 * dan pemetaan batch di masa depan. Terisi otomatis saat pertama kali diajukan, admin bisa mengubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->unsignedTinyInteger('enrollment_month')->nullable()->after('passport_number'); // 1–12
            $table->unsignedSmallInteger('enrollment_year')->nullable()->after('enrollment_month');

            $table->index(['enrollment_year', 'enrollment_month']);
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex(['enrollment_year', 'enrollment_month']);
            $table->dropColumn(['enrollment_month', 'enrollment_year']);
        });
    }
};
