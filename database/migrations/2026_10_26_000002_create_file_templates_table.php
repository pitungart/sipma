<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Template berkas yang diunggah Super Admin (Sistem → Unggahan berkas): satu per jenis dokumen
 * pendaftar, ditambah template MOU agen. Belum ada baris = belum ada template.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_templates', function (Blueprint $table) {
            $table->string('name')->primary(); // nilai App\Enums\DocumentType atau "mou"
            $table->string('file_path');
            $table->string('original_name');
            $table->unsignedInteger('file_size');
            $table->string('mime_type')->nullable();
            $table->foreignUuid('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_templates');
    }
};
