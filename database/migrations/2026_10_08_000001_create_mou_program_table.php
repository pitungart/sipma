<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Program yang dicakup MOU agen (keputusan 8 Oktober 2026, "Perlu keputusan" #1 = A): KUI mencentang
 * program saat menyetujui MOU; agen hanya bisa mendaftarkan mahasiswa ke program tersebut.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mou_program', function (Blueprint $table) {
            $table->foreignUuid('mou_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('program_id')->constrained()->restrictOnDelete();
            $table->primary(['mou_id', 'program_id']);
        });

        // MOU yang sudah disetujui sebelum fitur ini ada: dianggap mencakup semua program aktif
        $programs = DB::table('programs')->where('is_active', true)->whereNull('deleted_at')->pluck('id');
        $approved = DB::table('mous')->where('status', 'approved')->pluck('id');

        DB::table('mou_program')->insert($approved->crossJoin($programs)
            ->map(fn (array $pair): array => ['mou_id' => $pair[0], 'program_id' => $pair[1]])
            ->all());
    }

    public function down(): void
    {
        Schema::dropIfExists('mou_program');
    }
};
