<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catatan pengingat terjadwal yang sudah terkirim (sipma:reminders) agar tidak ganda:
 * "belum diajukan" sekali per penerima × periode × H-sekian, "belum bayar" berjeda per mahasiswa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sent_reminders', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 20); // submission | payment
            $table->string('key'); // mis. "{user}:{periode}:{hari}" atau "{mahasiswa}"
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete(); // penerima
            $table->timestamp('sent_at');
            $table->index(['kind', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sent_reminders');
    }
};
