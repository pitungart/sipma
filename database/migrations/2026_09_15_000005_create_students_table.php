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
        Schema::create('students', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->unique()->constrained()->nullOnDelete(); // pendaftar mandiri
            $table->foreignUuid('agent_id')->nullable()->constrained()->nullOnDelete(); // pendaftar via agen
            $table->foreignUuid('program_id')->constrained();
            $table->string('full_name');
            $table->string('gender', 10)->nullable(); // App\Enums\Gender
            $table->string('place_of_birth')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('nationality', 100)->nullable();
            $table->string('religion', 100)->nullable();
            $table->text('permanent_address')->nullable();
            $table->string('state', 100)->nullable();
            $table->string('post_code', 20)->nullable();
            $table->string('email');
            $table->string('phone_number', 50)->nullable();
            $table->string('home_university')->nullable();
            $table->string('country_of_home_university', 100)->nullable();
            $table->string('passport_number', 50);
            $table->date('date_of_issued_passport')->nullable();
            $table->date('date_of_passport_expiry')->nullable();
            $table->string('photo')->nullable();
            $table->string('status', 20)->default('draft')->index(); // App\Enums\StudentStatus
            $table->text('revision_note')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
