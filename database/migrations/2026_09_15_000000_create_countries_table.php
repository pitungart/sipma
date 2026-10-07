<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master negara ISO 3166-1 alpha-2. Primary key memakai kode ISO (bukan UUID) karena kodenya
 * baku, stabil, dan terbaca langsung di database. Diisi CountrySeeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->char('code', 2)->primary(); // ISO 3166-1 alpha-2, mis. ID, AU, JP
            $table->string('name_en', 100);
            $table->string('name_id', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
