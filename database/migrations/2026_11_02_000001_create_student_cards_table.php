<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * KTM digital (TP-2). Satu mahasiswa bisa punya banyak baris (riwayat terbit ulang);
     * "satu KTM aktif per mahasiswa" dijaga StudentCardWorkflow, bukan unique index.
     */
    public function up(): void
    {
        Schema::create('student_cards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('student_id')->constrained()->cascadeOnDelete();
            $table->string('card_number', 50)->unique();
            $table->date('issued_at');
            $table->date('valid_until');
            $table->string('status');
            $table->foreignUuid('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoke_reason')->nullable();
            $table->foreignUuid('replaces_id')->nullable()->constrained('student_cards')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'status']);
        });

        Schema::table('programs', function (Blueprint $table) {
            $table->unsignedTinyInteger('duration_months')->nullable()->after('tuition_fee');
        });
    }

    public function down(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->dropColumn('duration_months');
        });

        Schema::dropIfExists('student_cards');
    }
};
