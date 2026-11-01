<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Posisi foto di dalam lingkaran avatar ({x, y, zoom, ratio}). Berkas foto tidak dipotong,
     * sehingga Pas Foto 3×4 mahasiswa (dokumen resmi) tetap utuh.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('avatar_focus')->nullable()->after('avatar_path');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('avatar_focus');
        });
    }
};
