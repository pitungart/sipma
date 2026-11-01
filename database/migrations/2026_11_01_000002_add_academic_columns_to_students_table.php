<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tahap pasca-LOA (TP-0): kapan & oleh siapa mahasiswa ditandai Aktif, dan kapan programnya selesai.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->timestamp('enrolled_at')->nullable()->after('submitted_at');
            $table->foreignUuid('enrolled_by')->nullable()->after('enrolled_at')->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable()->after('enrolled_by');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropConstrainedForeignId('enrolled_by');
            $table->dropColumn(['enrolled_at', 'completed_at']);
        });
    }
};
