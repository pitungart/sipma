<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penomoran dinamis (Sistem → Penomoran): susunan atribut per jenis nomor + penghitung per periode,
     * serta kolom nomor baru untuk pendaftaran, MOU, dan kwitansi pembayaran.
     */
    public function up(): void
    {
        Schema::create('number_formats', function (Blueprint $table) {
            $table->id();
            $table->string('type', 30)->unique(); // App\Enums\NumberType
            $table->string('separator', 12)->default('slash'); // App\Enums\NumberSeparator
            $table->json('segments'); // atribut berurutan, lihat App\Models\NumberFormat
            $table->timestamps();
        });

        // Satu baris per jenis per periode; dikunci (lockForUpdate) saat nomor diambil
        Schema::create('number_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('type', 30);
            $table->string('period', 10); // 2026 | 2026-10 | all
            $table->unsignedInteger('last_value')->default(0);
            $table->timestamps();

            $table->unique(['type', 'period']);
        });

        Schema::table('students', function (Blueprint $table) {
            $table->string('registration_number', 100)->nullable()->unique()->after('id'); // diberikan saat diajukan
        });

        Schema::table('mous', function (Blueprint $table) {
            $table->string('mou_number', 100)->nullable()->unique()->after('agent_id'); // diberikan saat disetujui
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('receipt_number', 100)->nullable()->unique()->after('student_id'); // diberikan saat diverifikasi
        });
    }

    public function down(): void
    {
        // Indeks unik dihapus dulu: SQLite tidak bisa menghapus kolom yang masih berindeks
        foreach (['payments' => 'receipt_number', 'mous' => 'mou_number', 'students' => 'registration_number'] as $table => $column) {
            Schema::table($table, function (Blueprint $blueprint) use ($column) {
                $blueprint->dropUnique([$column]);
                $blueprint->dropColumn($column);
            });
        }

        Schema::dropIfExists('number_sequences');
        Schema::dropIfExists('number_formats');
    }
};
