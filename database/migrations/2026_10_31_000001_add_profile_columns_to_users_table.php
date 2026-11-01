<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Halaman Profil saya: foto unggahan sendiri (admin & agen; mahasiswa memakai Pas Foto 3×4)
     * dan pilihan email per jenis notifikasi (null = semua aktif).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar_path')->nullable()->after('locale');
            $table->json('notification_preferences')->nullable()->after('avatar_path');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['avatar_path', 'notification_preferences']);
        });
    }
};
