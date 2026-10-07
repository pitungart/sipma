<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master rekening/VA statis tujuan pembayaran, menggantikan VA yang diketik manual.
 * Semua nominal dalam Rupiah, jadi tidak ada kolom mata uang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('bank_name', 100);
            $table->string('account_name');
            $table->string('va_number', 50)->unique();
            $table->string('fee_type', 20)->nullable(); // App\Enums\PaymentType; null = semua jenis biaya
            $table->foreignUuid('program_id')->nullable()->constrained()->nullOnDelete(); // null = semua program
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_accounts');
    }
};
