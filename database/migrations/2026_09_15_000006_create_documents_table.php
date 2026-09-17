<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('student_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30); // App\Enums\DocumentType
            $table->string('file_path');
            $table->string('original_name')->nullable();
            $table->integer('file_size')->nullable(); // bytes, max 300KB
            $table->string('mime_type', 100)->nullable();
            $table->string('status', 20)->default('pending')->index(); // App\Enums\DocumentStatus
            $table->text('revision_note')->nullable();
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
